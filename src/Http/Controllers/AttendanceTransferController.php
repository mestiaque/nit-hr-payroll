<?php

namespace ME\Hr\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\Hr\Models\HrAttendance;
use ME\Hr\Models\HrEmployee;
use ME\Hr\Models\HrShift;
use ME\Hr\Services\AttendanceTransferService;

/**
 * Excel/CSV export of stored attendance rows, and bulk import of in/out times
 * (one row per employee per day) through the same rules as a manual edit:
 * locked periods, exit dates, status/OT/worked-minute calculation.
 */
class AttendanceTransferController extends Controller
{
    private const EXPORT_HEADERS = [
        'Employee ID', 'Name', 'Department', 'Section', 'Designation', 'Shift', 'Date', 'Day',
        'In Time', 'Out Time', 'Status', 'Working Hours', 'OT Hours', 'Remarks', 'Via',
    ];

    public function __construct(private readonly AttendanceTransferService $files)
    {
    }

    public function export(Request $request)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'format'    => 'nullable|in:xlsx,csv',
        ]);

        // The list screen shows a single day when no range is picked — export the same.
        $from = $request->input('date_from') ?: $request->input('date', Carbon::today()->toDateString());
        $to   = $request->input('date_to') ?: $from;

        $employeeIds = AttendanceController::filteredEmployeesQuery($request)->select('id');

        $query = DB::table('hr_attendances as a')
            ->join('hr_employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('hr_departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('hr_sections as s', 's.id', '=', 'e.section_id')
            ->leftJoin('hr_designations as g', 'g.id', '=', 'e.designation_id')
            ->leftJoin('hr_shifts as sh', 'sh.id', '=', 'e.shift_id')
            ->whereIn('a.employee_id', $employeeIds)
            ->whereBetween('a.date', [$from, $to])
            ->when($request->input('status'), fn ($q, $status) => $q->where('a.status', $status))
            ->select([
                'a.date', 'a.in_time', 'a.out_time', 'a.status', 'a.total_working_minute', 'a.total_ot_minute', 'a.remarks', 'a.via',
                'e.employee_id as employee_code', 'e.name as employee_name', 'd.name as department', 's.name as section',
                'g.name as designation', 'sh.name as shift',
            ])
            ->orderByRaw("CAST(REGEXP_REPLACE(e.employee_id, '[^0-9]', '') AS UNSIGNED) ASC")
            ->orderBy('e.employee_id')
            ->orderBy('a.date');

        $hours = fn ($minutes) => $minutes ? sprintf('%d:%02d', intdiv((int) $minutes, 60), (int) $minutes % 60) : '';
        $time  = fn ($value) => $value ? Carbon::parse($value)->format('H:i') : '';

        $toRow = fn (object $a) => [
            $a->employee_code,
            $a->employee_name,
            $a->department,
            $a->section,
            $a->designation,
            $a->shift,
            Carbon::parse($a->date)->format('Y-m-d'),
            Carbon::parse($a->date)->format('D'),
            $time($a->in_time),
            $time($a->out_time),
            $a->status,
            $hours($a->total_working_minute),
            $hours($a->total_ot_minute),
            $a->remarks,
            $a->via,
        ];

        $filename = "attendance-{$from}-to-{$to}";

        if ($request->input('format') === 'csv' || !AttendanceTransferService::spreadsheetAvailable()) {
            return $this->files->downloadDelimited("{$filename}.csv", self::EXPORT_HEADERS, function ($write) use ($query, $toRow) {
                foreach ($query->cursor() as $attendance) {
                    $write($toRow($attendance));
                }
            });
        }

        $rows = (function () use ($query, $toRow) {
            foreach ($query->cursor() as $attendance) {
                yield $toRow($attendance);
            }
        })();

        return $this->files->downloadSpreadsheet(
            $this->files->buildSpreadsheet('Attendance', self::EXPORT_HEADERS, $rows),
            "{$filename}.xlsx"
        );
    }

    public function create()
    {
        return view('hr::attendances.import');
    }

    public function template(Request $request)
    {
        $date = Carbon::yesterday();
        $employees = HrEmployee::query()
            ->where('status', 1)
            ->where(fn ($q) => $q->whereNull('employment_status')->orWhereIn('employment_status', ['', 'regular']))
            ->naturalOrderById()
            ->limit($request->boolean('all') ? 100000 : 5)
            ->get(['employee_id', 'shift_id'])
            ->load('shift:id,start_time,end_time');

        $rows = $employees->map(fn ($e) => [
            $e->employee_id,
            $date->toDateString(),
            $e->shift?->start_time ? Carbon::parse($e->shift->start_time)->format('H:i') : '09:00',
            $e->shift?->end_time ? Carbon::parse($e->shift->end_time)->format('H:i') : '18:00',
            '',
        ])->all();

        $headers = array_values(AttendanceTransferService::ATTENDANCE_IMPORT_COLUMNS);
        $notes = [
            'Attendance import — one row per employee per day.',
            'Required: Employee ID and Date, plus at least one of In Time / Out Time.',
            'Date formats: 2026-09-01, 01/09/2026, 01-09-2026 (day first), or an Excel date cell.',
            'Time formats: 09:05, 09:05:30, 9:05 AM, or an Excel time cell. An Out Time earlier than In Time is treated as next day (night shift).',
            'Status, OT and working hours are calculated automatically from the employee\'s shift — the same as editing attendance by hand.',
            'A file exported from the Attendance List can be edited and imported back directly; extra columns are ignored.',
            'Locked periods, dates after an employee\'s exit date, and future dates are skipped and listed in the result.',
        ];

        if (!AttendanceTransferService::spreadsheetAvailable()) {
            return $this->files->downloadDelimited('attendance-import-template.csv', $headers, fn ($write) => array_map($write, $rows));
        }

        return $this->files->downloadSpreadsheet(
            $this->files->buildSpreadsheet('Attendance', $headers, $rows, $notes),
            'attendance-import-template.xlsx'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'attendance_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'mode'            => 'required|in:overwrite,skip',
        ], [
            'attendance_file.mimes' => 'Only .xlsx, .xls or .csv files can be imported.',
        ]);

        @set_time_limit(900);
        @ini_set('memory_limit', '1024M');

        $rows = $this->files->readRows($request->file('attendance_file'));
        $header = $this->files->extractHeaderMap($rows, array_keys(AttendanceTransferService::ATTENDANCE_IMPORT_COLUMNS));

        if (!$header || !isset($header['date'])) {
            return back()->with('error', 'The first row must be a header with at least "Employee ID" and "Date" columns. Download the template to see the layout.');
        }
        if (!isset($header['in_time']) && !isset($header['out_time'])) {
            return back()->with('error', 'The file needs an "In Time" and/or "Out Time" column.');
        }

        $overwrite = $request->input('mode') === 'overwrite';
        $employees = HrEmployee::query()->get()->keyBy('employee_id');
        $shifts = HrShift::all()->keyBy('id');
        $today = Carbon::today()->toDateString();

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'blank' => 0, 'failed' => 0];
        $problems = [];
        $seen = [];

        $get = fn (array $cells, string $field) => isset($header[$field]) ? trim((string) ($cells[$header[$field]] ?? '')) : '';

        DB::transaction(function () use ($rows, $header, $get, $employees, $shifts, $overwrite, $today, &$stats, &$problems, &$seen) {
            foreach ($rows as $row) {
                $stats['total']++;
                $cells = $row['cells'];
                $employeeCode = $get($cells, 'employee_id');
                $fail = function (string $reason, string $bucket = 'failed') use (&$stats, &$problems, $row, $employeeCode) {
                    $stats[$bucket]++;
                    $problems[] = [$row['line'], $employeeCode ?: '-', $reason];
                };

                if ($employeeCode === '') {
                    $fail('Employee ID is missing');
                    continue;
                }

                $employee = $employees->get($employeeCode);
                if (!$employee) {
                    $fail('No employee has this ID');
                    continue;
                }

                $date = $this->files->normalizeDate($get($cells, 'date'));
                if (!$date) {
                    $fail('Date "' . $get($cells, 'date') . '" could not be read');
                    continue;
                }
                if ($date > $today) {
                    $fail("$date is in the future", 'skipped');
                    continue;
                }

                $rawIn = $get($cells, 'in_time');
                $rawOut = $get($cells, 'out_time');
                $in = $this->files->normalizeTime($rawIn);
                $out = $this->files->normalizeTime($rawOut);
                if (($rawIn !== '' && !$in) || ($rawOut !== '' && !$out)) {
                    $fail('Time "' . ($rawIn !== '' && !$in ? $rawIn : $rawOut) . '" could not be read');
                    continue;
                }
                // Rows with neither time (e.g. "Absent" rows of an exported sheet) carry
                // nothing to save — counted, but not listed as problems.
                if (!$in && !$out) {
                    $stats['blank']++;
                    continue;
                }

                $key = $employee->id . '|' . $date;
                if (isset($seen[$key])) {
                    $fail("Duplicate row for $date (first row {$seen[$key]} was used)", 'skipped');
                    continue;
                }
                $seen[$key] = $row['line'];

                $existing = HrAttendance::where('employee_id', $employee->id)->whereDate('date', $date)->first();

                if (AttendanceController::isAttendanceLocked($employee, $date, $existing)) {
                    $fail("$date is locked", 'skipped');
                    continue;
                }
                $exitedAt = AttendanceController::resolveExitedAt($employee);
                if ($exitedAt && Carbon::parse($date)->gt($exitedAt)) {
                    $fail('Employee exited on ' . $exitedAt->toDateString(), 'skipped');
                    continue;
                }
                if ($existing && !$overwrite) {
                    $fail("Attendance for $date already exists", 'skipped');
                    continue;
                }

                $attendance = $existing ?? new HrAttendance(['employee_id' => $employee->id, 'date' => $date]);
                $attendance->in_time = $in;
                $attendance->out_time = $out;
                if (isset($header['remarks'])) {
                    $attendance->remarks = $get($cells, 'remarks') ?: null;
                }
                if (!$existing) {
                    $attendance->via = 'import';
                }

                AttendanceController::applyManualTimes($attendance, $employee, $shifts->get($employee->shift_id));
                $attendance->save();

                $stats[$existing ? 'updated' : 'created']++;
            }
        });

        $result = [
            'title' => 'Attendance import finished',
            'stats' => [
                'Rows in file'   => $stats['total'],
                'Created'        => $stats['created'],
                'Updated'        => $stats['updated'],
                'Skipped'        => $stats['skipped'],
                'No in/out time' => $stats['blank'],
                'Errors'         => $stats['failed'],
            ],
            'problems' => array_slice($problems, 0, 200),
            'more'     => max(0, count($problems) - 200),
        ];

        $saved = $stats['created'] + $stats['updated'];
        $flash = $saved > 0 ? 'success' : 'error';
        $message = $saved > 0
            ? "Attendance saved for {$saved} row(s) ({$stats['created']} new, {$stats['updated']} updated)."
            : ($stats['total'] === 0 ? 'The file has no rows to import.' : 'No attendance was saved — see the details below.');

        return redirect()->route('hr-center.attendances.import')->with($flash, $message)->with('import_result', $result);
    }
}
