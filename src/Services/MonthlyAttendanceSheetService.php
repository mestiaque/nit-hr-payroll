<?php

namespace ME\Hr\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ME\Hr\Models\HrLeaveInfo;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Employee x day attendance grid (one code per day, e.g. P / L / A / CL / W / H) for a
 * date range, built on EmployeeAttendanceService so every code agrees with the Job Card
 * and salary sheet. Feeds both the print view and a native, colour-coded .xlsx.
 */
class MonthlyAttendanceSheetService
{
    /** Totals columns shown after the day columns: key => heading */
    public const TOTALS = [
        'present' => 'Present',
        'late' => 'Late',
        'absent' => 'Absent',
        'leave' => 'Leave',
        'weekend' => 'Weekend',
        'holiday' => 'Holiday',
        'days' => 'Total Days',
    ];

    /** Short headings for the narrow print columns (same keys as TOTALS) */
    public const TOTALS_SHORT = [
        'present' => 'Pres.',
        'late' => 'Late',
        'absent' => 'Abs.',
        'leave' => 'Leave',
        'weekend' => 'W/E',
        'holiday' => 'Hol.',
        'days' => 'Days',
    ];

    /** Cell colours by code category (hex RGB, no '#') */
    private const COLORS = [
        'P' => null,
        'L' => 'FCE4D6',
        'EO' => 'FFF2CC',
        'PM' => 'FFF2CC',
        'A' => 'FF9F9F',
        'W' => 'FFFF00',
        'H' => 'FFD966',
        'FH' => 'F4B183',
        'leave' => 'BDD7EE',
        '' => 'E7E6E6',
    ];

    /**
     * @return array{days: array, rows: array<int, array{codes: array, totals: array}>}
     */
    public function build(Collection $employees, string $from, string $to): array
    {
        $days = [];
        for ($d = Carbon::parse($from)->startOfDay(), $end = Carbon::parse($to)->startOfDay(); $d->lte($end); $d->addDay()) {
            $days[$d->toDateString()] = [
                'date' => $d->toDateString(),
                'day' => $d->format('j'),
                'dow' => $d->format('D'),
                'holiday' => false,
            ];
        }

        $rows = [];
        foreach ($employees as $employee) {
            $pack = EmployeeAttendanceService::getEmployeeAttendanceByDate($employee->id, $from, $to);
            $codes = [];
            $totals = array_fill_keys(array_keys(self::TOTALS), 0);

            foreach ($pack['attendance'] ?? [] as $row) {
                try {
                    $date = Carbon::createFromFormat('d-m-Y', (string) $row['date'])->toDateString();
                } catch (\Throwable) {
                    continue;
                }

                $code = $this->codeFor($row);
                $codes[$date] = $code;

                if ($code === '') {
                    continue;
                }
                $totals['days']++;
                match ($this->category($code)) {
                    'P', 'EO', 'PM' => $totals['present']++,
                    'L' => [$totals['present']++, $totals['late']++],
                    'A' => $totals['absent']++,
                    'leave' => $totals['leave']++,
                    'W' => $totals['weekend']++,
                    'H', 'FH' => $totals['holiday']++,
                    default => null,
                };
                if (in_array($code, ['H', 'FH'], true) && isset($days[$date])) {
                    $days[$date]['holiday'] = true;
                }
            }

            $rows[$employee->id] = ['codes' => $codes, 'totals' => $totals];
        }

        return ['days' => array_values($days), 'rows' => $rows];
    }

    /**
     * Code => label, in display order, including every leave type defined in the software.
     */
    public function legend(): array
    {
        $legend = [
            'P' => 'Present',
            'L' => 'Late',
            'EO' => 'Early Exit',
            'PM' => 'Punch Missing',
            'A' => 'Absent',
            'W' => 'Weekend',
            'H' => 'General Holiday',
            'FH' => 'Festival Holiday',
        ];
        foreach (HrLeaveInfo::query()->orderBy('id')->get(['name', 'code']) as $leaveType) {
            if (filled($leaveType->code)) {
                $legend[strtoupper($leaveType->code)] = $leaveType->name;
            }
        }
        $legend['LV'] = 'Leave (type not set)';

        return $legend;
    }

    /** Background colour (hex, or null for none) for a code. */
    public function color(string $code): ?string
    {
        return self::COLORS[$this->category($code)] ?? null;
    }

    private function category(string $code): string
    {
        return array_key_exists($code, self::COLORS) ? $code : 'leave';
    }

    private function codeFor(array $row): string
    {
        $status = strtolower((string) ($row['status_key'] ?? ''));

        return match (true) {
            $status === 'not_employed' => '',
            $status === 'leave' => strtoupper((string) ($row['leave_code'] ?? '')) ?: 'LV',
            $status === 'weekend' => 'W',
            $status === 'holiday' => strtolower((string) ($row['holiday_type'] ?? '')) === 'festival' ? 'FH' : 'H',
            $status === 'absent' => 'A',
            in_array($status, ['late', 'late_and_early_exit', 'leo', 'late_and_punch_missing', 'lpm'], true) => 'L',
            in_array($status, ['early_exit', 'eo'], true) => 'EO',
            in_array($status, ['punch_missing', 'pm'], true) => 'PM',
            default => 'P',
        };
    }

    /**
     * @param array $groups list of ['label' => ?string, 'employees' => iterable]
     * @param array $meta   company, address, title, period
     */
    public function spreadsheet(array $sheetData, array $groups, array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Sheet');

        $days = $sheetData['days'];
        $fixed = ['Employee ID', 'Name', 'Department', 'Designation', 'Join Date'];
        $dayStart = count($fixed) + 1;
        $totalStart = $dayStart + count($days);
        $lastCol = $totalStart + count(self::TOTALS) - 1;
        $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);
        $last = $col($lastCol);

        // Title block — same as the other HR report headers: company, address, then
        // report title with its date range.
        $titleRows = [
            1 => [$meta['company'] ?? '', 15, true],
            2 => [$meta['address'] ?? '', 10, false],
            4 => [trim(($meta['title'] ?? 'Monthly Attendance Sheet') . ' — ' . ($meta['period'] ?? ''), ' —'), 12, true],
        ];
        foreach ($titleRows as $r => [$text, $size, $bold]) {
            $sheet->mergeCells("A{$r}:{$last}{$r}");
            $sheet->setCellValueExplicit("A{$r}", (string) $text, DataType::TYPE_STRING);
            $style = $sheet->getStyle("A{$r}:{$last}{$r}");
            $style->getFont()->setBold($bold)->setSize($size);
            $style->getAlignment()->setHorizontal($r === 4 ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER);
        }

        // Two header rows: day number over weekday name; the other headings span both.
        $h1 = 6;
        $h2 = 7;
        foreach ($fixed as $i => $heading) {
            $c = $col($i + 1);
            $sheet->mergeCells("{$c}{$h1}:{$c}{$h2}");
            $sheet->setCellValue("{$c}{$h1}", $heading);
        }
        foreach ($days as $i => $day) {
            $c = $col($dayStart + $i);
            $sheet->setCellValue("{$c}{$h1}", (int) $day['day']);
            $sheet->setCellValue("{$c}{$h2}", $day['dow']);
            if ($day['holiday']) {
                $sheet->getStyle("{$c}{$h1}:{$c}{$h2}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLORS['H']);
            }
        }
        $i = 0;
        foreach (self::TOTALS as $heading) {
            $c = $col($totalStart + $i++);
            $sheet->mergeCells("{$c}{$h1}:{$c}{$h2}");
            $sheet->setCellValue("{$c}{$h1}", $heading);
        }
        $headerStyle = $sheet->getStyle("A{$h1}:{$last}{$h2}");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        foreach ([$col(1).$h1.':'.$col($dayStart - 1).$h2, $col($totalStart).$h1.':'.$last.$h2] as $range) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF1D4');
        }

        // Body
        $r = $h2;
        $groupRows = [];
        foreach ($groups as $group) {
            if (filled($group['label'] ?? null)) {
                $r++;
                $groupRows[] = $r;
                $sheet->mergeCells("A{$r}:{$last}{$r}");
                $sheet->setCellValueExplicit("A{$r}", (string) $group['label'], DataType::TYPE_STRING);
                $style = $sheet->getStyle("A{$r}:{$last}{$r}");
                $style->getFont()->setBold(true);
                $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDE6F0');
            }

            foreach ($group['employees'] as $employee) {
                $r++;
                $data = $sheetData['rows'][$employee->id] ?? ['codes' => [], 'totals' => []];
                $sheet->setCellValueExplicit("A{$r}", (string) $employee->employee_id, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("B{$r}", (string) $employee->name, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("C{$r}", (string) (optional($employee->department)->name ?? 'N/A'), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$r}", (string) (optional($employee->designation)->name ?? 'N/A'), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("E{$r}", $employee->joining_date ? $employee->joining_date->format('d-M-y') : '-', DataType::TYPE_STRING);

                foreach ($days as $i => $day) {
                    $code = $data['codes'][$day['date']] ?? '';
                    $cell = $col($dayStart + $i).$r;
                    $sheet->setCellValueExplicit($cell, $code, DataType::TYPE_STRING);
                    if ($color = $this->color($code)) {
                        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                    }
                    if ($code === 'A') {
                        $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('C00000');
                    }
                }
                $i = 0;
                foreach (array_keys(self::TOTALS) as $key) {
                    $sheet->setCellValue($col($totalStart + $i++).$r, (int) ($data['totals'][$key] ?? 0));
                }
            }
        }

        $tableRange = "A{$h1}:{$last}{$r}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('A6A6A6');
        $sheet->getStyle($col($dayStart).($h2 + 1).":{$last}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($col($totalStart).($h2 + 1).":{$last}{$r}")->getFont()->setBold(true);

        // Legend
        $r += 2;
        $sheet->setCellValue("A{$r}", 'Legend');
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        foreach ($this->legend() as $code => $label) {
            $r++;
            $sheet->setCellValueExplicit("A{$r}", $code, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$r}", $label, DataType::TYPE_STRING);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($color = $this->color($code)) {
                $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
        }
        $r++;
        $sheet->setCellValueExplicit("B{$r}", 'Blank (grey) = before joining / after exit / future date', DataType::TYPE_STRING);
        $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLORS['']);

        // Sizing, freeze and print setup
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getStyle('A'.($h2 + 1).":A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E'.($h2 + 1).":E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach ($groupRows as $groupRow) {
            $sheet->getStyle("A{$groupRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        for ($c = $dayStart; $c < $totalStart; $c++) {
            $sheet->getColumnDimension($col($c))->setWidth(4.6);
        }
        for ($c = $totalStart; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension($col($c))->setWidth(9.5);
        }
        $sheet->getRowDimension($h1)->setRowHeight(18);
        $sheet->freezePane($col($dayStart).($h2 + 1));
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($h1, $h2);

        return $spreadsheet;
    }
}
