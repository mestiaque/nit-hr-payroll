<?php

namespace ME\Hr\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File plumbing shared by the machine-log and attendance import/export screens:
 * reading .xlsx/.xls/.csv/.dat/.txt uploads into plain rows, normalising the many
 * date/time shapes those files carry (Excel serials, d/m/Y, 12-hour clocks, ...),
 * and building the downloadable sheets.
 */
class AttendanceTransferService
{
    public const MACHINE_LOG_COLUMNS = [
        'employee_id' => 'Employee ID',
        'log_time'    => 'Log Time',
        'device_sn'   => 'Device SN',
        'type_code'   => 'Type Code',
        'type_name'   => 'Verify Method',
        'source'      => 'Source',
        'work_code'   => 'Work Code',
    ];

    public const ATTENDANCE_IMPORT_COLUMNS = [
        'employee_id' => 'Employee ID',
        'date'        => 'Date',
        'in_time'     => 'In Time',
        'out_time'    => 'Out Time',
        'remarks'     => 'Remarks',
    ];

    /** Header spellings accepted for each field (compared after lower-casing and stripping non-alphanumerics). */
    private const HEADER_ALIASES = [
        'employee_id' => ['employeeid', 'empid', 'employee', 'userid', 'user', 'cardno', 'idno', 'id'],
        'log_time'    => ['logtime', 'datetime', 'punchtime', 'timestamp', 'time'],
        'date'        => ['date', 'attendancedate', 'logdate', 'punchdate'],
        'device_sn'   => ['devicesn', 'device', 'deviceid', 'sn', 'terminal', 'terminalid', 'machine'],
        'type_code'   => ['typecode', 'statuscode', 'state', 'status'],
        'type_name'   => ['verifymethod', 'verifytype', 'typename', 'verify'],
        'source'      => ['source', 'via'],
        'work_code'   => ['workcode'],
        'in_time'     => ['intime', 'in', 'checkin', 'punchin'],
        'out_time'    => ['outtime', 'out', 'checkout', 'punchout'],
        'remarks'     => ['remarks', 'remark', 'note', 'notes', 'comment'],
    ];

    public static function spreadsheetAvailable(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    // ------------------------------------------------------------------ reading

    /**
     * Returns every non-empty row of the upload as a list of trimmed string cells.
     * Spreadsheet date/time cells come back as Excel serial numbers (as strings) —
     * the normalise*() helpers below understand those.
     *
     * @return list<array{line:int, cells:list<string>}>
     */
    public function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            if (!self::spreadsheetAvailable()) {
                abort(501, 'Excel import requires the phpoffice/phpspreadsheet package.');
            }

            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            $raw = $sheet->toArray(null, true, false, false);

            return $this->cleanRows($raw);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        if (isset($lines[0])) {
            $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
        }

        $isCsv = $extension === 'csv';
        $delimiter = $isCsv ? $this->detectDelimiter($lines) : null;

        $raw = array_map(function (string $line) use ($isCsv, $delimiter) {
            if ($isCsv) {
                return str_getcsv($line, $delimiter);
            }
            // ZKTeco attlog .dat/.txt: whitespace/tab/comma separated
            return preg_split('/[\s,]+/', trim($line)) ?: [];
        }, $lines);

        return $this->cleanRows($raw);
    }

    private function cleanRows(array $raw): array
    {
        $rows = [];
        foreach ($raw as $index => $cells) {
            $cells = array_map(fn ($c) => trim((string) ($c ?? '')), (array) $cells);
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = ['line' => $index + 1, 'cells' => array_values($cells)];
        }

        return $rows;
    }

    private function detectDelimiter(array $lines): string
    {
        $sample = implode("\n", array_slice($lines, 0, 5));
        $counts = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * If the first row looks like a header, returns [field => column index] for the
     * requested fields and removes that row from $rows. Returns null for headerless files.
     *
     * @param list<string> $fields
     */
    public function extractHeaderMap(array &$rows, array $fields): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $header = array_map(fn ($c) => preg_replace('/[^a-z0-9]/', '', strtolower($c)), $rows[0]['cells']);
        $map = [];

        foreach ($fields as $field) {
            foreach (self::HEADER_ALIASES[$field] ?? [$field] as $alias) {
                $index = array_search($alias, $header, true);
                if ($index !== false && !in_array($index, $map, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        // Only treat it as a header when the employee column is recognised — a data
        // row's first cell is an ID like "1001", never a word like "Employee ID".
        if (!isset($map['employee_id'])) {
            return null;
        }

        array_shift($rows);

        return $map;
    }

    // -------------------------------------------------------------- normalising

    public function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd-M-Y', 'd M Y', 'j/n/Y', 'j-n-Y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function normalizeTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Excel time fraction (0.375 = 09:00) or full serial datetime (46265.375)
        if (is_numeric($value)) {
            $fraction = fmod((float) $value, 1.0);
            $seconds = (int) round($fraction * 86400) % 86400;

            return gmdate('H:i:s', $seconds);
        }

        $value = strtoupper(preg_replace('/\s+/', ' ', $value));
        foreach (['H:i:s', 'H:i', 'h:i:s A', 'h:i A', 'g:i:s A', 'g:i A', 'h:iA', 'g:iA'] as $format) {
            $time = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if ($time && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $time->format('H:i:s');
            }
        }

        return null;
    }

    /** Accepts "Y-m-d H:i:s", "d/m/Y h:i A", ISO "…T…", an Excel serial, or separate date + time parts. */
    public function normalizeDateTime(?string $value, ?string $timePart = null): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            $date = $this->normalizeDate($value);
            $time = $timePart !== null && $timePart !== '' ? $this->normalizeTime($timePart) : $this->normalizeTime($value);

            return $date && $time ? "$date $time" : null;
        }

        if ($timePart !== null && $timePart !== '') {
            $date = $this->normalizeDate($value);
            $time = $this->normalizeTime($timePart);

            return $date && $time ? "$date $time" : null;
        }

        $value = str_replace('T', ' ', preg_replace('/(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', '', $value));
        $parts = explode(' ', $value, 2);
        if (count($parts) === 2) {
            $date = $this->normalizeDate($parts[0]);
            $time = $this->normalizeTime($parts[1]);
            if ($date && $time) {
                return "$date $time";
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ writing

    /**
     * @param list<string> $headers
     * @param iterable<list<mixed>> $rows
     */
    public function buildSpreadsheet(string $title, array $headers, iterable $rows, array $notes = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));

        $sheet->fromArray($headers, null, 'A1');
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEBF7');
        $sheet->freezePane('A2');

        $rowNumber = 2;
        foreach ($rows as $row) {
            // Write every cell as text so IDs like "0012" and times like "09:00:00" survive a round trip.
            foreach (array_values($row) as $col => $value) {
                $sheet->setCellValueExplicit([$col + 1, $rowNumber], (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowNumber++;
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        if ($notes) {
            $help = $spreadsheet->createSheet();
            $help->setTitle('Instructions');
            foreach (array_values($notes) as $i => $note) {
                $help->setCellValue([1, $i + 1], $note);
            }
            $help->getColumnDimension('A')->setWidth(110);
            $spreadsheet->setActiveSheetIndex(0);
        }

        return $spreadsheet;
    }

    public function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * @param list<string>|null $headers  null = no header line (ZKTeco .dat)
     * @param callable(callable(list<mixed>):void):void $produce  receives a row writer
     */
    public function downloadDelimited(string $filename, ?array $headers, callable $produce, string $delimiter = ','): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $produce, $delimiter) {
            $out = fopen('php://output', 'w');
            if ($delimiter === ',') {
                fwrite($out, "\xEF\xBB\xBF"); // lets Excel open Bangla names correctly
            }
            if ($headers !== null) {
                fputcsv($out, $headers, $delimiter);
            }
            $produce(function (array $row) use ($out, $delimiter) {
                if ($delimiter === "\t") {
                    fwrite($out, implode("\t", array_map(fn ($v) => (string) ($v ?? ''), $row)) . "\r\n");
                } else {
                    fputcsv($out, $row, $delimiter);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type'  => $delimiter === ',' ? 'text/csv; charset=UTF-8' : 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
