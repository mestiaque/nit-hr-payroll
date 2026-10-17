<?php

namespace ME\Hr\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use ME\Hr\Models\HrAttendance;
use ME\Hr\Models\HrAttendanceMachineLog;
use ME\Hr\Models\HrAttendanceReplaceOff;
use ME\Hr\Models\HrEmployee;
use ME\Hr\Models\HrLock;
use ME\Hr\Models\HrShift;
use ME\Hr\Services\AttendanceTransferService;
use ME\Hr\Services\EmployeeAttendanceService;

class AttendanceMachineController extends Controller
{
    public function fetchEmployees(): JsonResponse
    {
        $employees = HrEmployee::where('status', true)
            // ->whereIn('employee_id', ['EMP-0001', 'EMP-0002', 'B00006'])
            ->naturalOrderById()
            ->get(['id', 'employee_id', 'name'])
            ->map(fn($e) => [
                'uid'         => $e->employee_id,
                'employee_id' => $e->employee_id,
                'name'        => $e->name,
                'privilege'   => 0,
                'password'    => '',
                'card'        => '',
            ]);

        return response()->json(['data' => $employees]);
    }

    public function receiveData(Request $request): JsonResponse
    {
        try {
            Log::info('ZKTeco Data Received', ['payload' => $request->all()]);

            $userId     = $request->input('user_id');
            $timestamp  = $request->input('time') ?? $request->input('timestamp');
            $sn         = $request->input('device_sn');
            $verifyType = $request->input('type_name');
            $typeCode   = $request->input('type_code');

            if (!$userId || !$timestamp) {
                return response()->json(['status' => 'error', 'message' => 'Invalid Data'], 400);
            }

            $this->saveMachineLog($userId, $timestamp, $sn, $verifyType, $typeCode);
            $this->saveAttendance($userId, $timestamp, $sn, $verifyType);

            return response()->json(['status' => 'success', 'message' => 'Attendance Processed']);

        } catch (\Throwable $e) {
            Log::error('ZKTeco receiveData error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function receiveBulkData(Request $request): JsonResponse
    {
        try {
            $data = $request->input('data');
            if (!is_array($data)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid Data Format'], 400);
            }

            foreach ($data as $entry) {
                $userId     = $entry['user_id'] ?? null;
                $timestamp  = $entry['time'] ?? $entry['timestamp'] ?? null;
                $sn         = $entry['device_sn'] ?? null;
                $verifyType = $entry['type_name'] ?? null;
                $typeCode   = $entry['type_code'] ?? null;

                if ($userId && $timestamp) {
                    $this->saveMachineLog($userId, $timestamp, $sn, $verifyType, $typeCode);
                    $this->saveAttendance($userId, $timestamp, $sn, $verifyType);
                }
            }

            return response()->json(['status' => 'success', 'message' => 'Bulk Attendance Processed']);

        } catch (\Throwable $e) {
            Log::error('ZKTeco receiveBulkData error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Receive ADMS-format bulk attendance records.
     *
     * Expected payload:
     * {
     *   "records": [
     *     { "deviceId", "employeeId", "time", "status", "verify",
     *       "verifyMethod", "workCode", "source", "receivedAt", "id", ... }
     *   ]
     * }
     *
     * All records are written to hr_attendance_machine_logs regardless of
     * whether the employee is found. If the employee IS found, attendance is
     * upserted using the employee's shift windows — see applyPunchToAttendance().
     */
    public function logs(Request $request)
    {
        $query = $this->filteredLogsQuery($request)->orderByDesc('log_time');

        $logs = $query->paginate(50)->withQueryString();

        // Map employee_id (device string) → hr_employees record for display
        $employeeIds = $logs->pluck('employee_id')->unique()->filter();
        $employees   = HrEmployee::whereIn('employee_id', $employeeIds)
                          ->get(['id', 'employee_id', 'name'])
                          ->keyBy('employee_id');

        $sources       = HrAttendanceMachineLog::select('source')->distinct()->whereNotNull('source')->pluck('source');
        $verifyMethods = HrAttendanceMachineLog::select('type_name')->distinct()->whereNotNull('type_name')->pluck('type_name');

        return view('hr::attendances.machineLog', compact('logs', 'employees', 'sources', 'verifyMethods'));
    }

    private function filteredLogsQuery(Request $request)
    {
        $query = HrAttendanceMachineLog::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%$search%")
                  ->orWhere('device_sn', 'like', "%$search%");
            });
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('log_time', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('log_time', '<=', $dateTo);
        }
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }
        if ($verifyMethod = $request->input('verify_method')) {
            $query->where('type_name', $verifyMethod);
        }

        return $query;
    }

    /**
     * Download the (filtered) machine logs. format=xlsx|csv, or dat for the
     * headerless ZKTeco attlog layout (ID  date time  device  type) that this
     * screen's own importer — and most attendance software — reads back.
     */
    public function export(Request $request, AttendanceTransferService $files)
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $format = strtolower((string) $request->input('format', 'xlsx'));
        $query = $this->filteredLogsQuery($request)->orderBy('log_time')->orderBy('id');
        $stamp = now()->format('Y-m-d_His');

        $toRow = fn (HrAttendanceMachineLog $log) => [
            $log->employee_id,
            Carbon::parse($log->log_time)->format('Y-m-d H:i:s'),
            $log->device_sn,
            $log->type_code,
            $log->type_name,
            $log->source,
            $log->work_code,
        ];

        if ($format === 'dat') {
            return $files->downloadDelimited("machine-logs-{$stamp}.dat", null, function ($write) use ($query) {
                $query->chunkById(2000, function ($logs) use ($write) {
                    foreach ($logs as $log) {
                        $time = Carbon::parse($log->log_time);
                        $write([$log->employee_id, $time->format('Y-m-d'), $time->format('H:i:s'), $log->device_sn, $log->type_code]);
                    }
                });
            }, "\t");
        }

        if ($format === 'csv' || !AttendanceTransferService::spreadsheetAvailable()) {
            return $files->downloadDelimited("machine-logs-{$stamp}.csv", array_values(AttendanceTransferService::MACHINE_LOG_COLUMNS), function ($write) use ($query, $toRow) {
                $query->chunkById(2000, function ($logs) use ($write, $toRow) {
                    foreach ($logs as $log) {
                        $write($toRow($log));
                    }
                });
            });
        }

        $rows = (function () use ($query, $toRow) {
            foreach ($query->lazyById(2000) as $log) {
                yield $toRow($log);
            }
        })();

        return $files->downloadSpreadsheet(
            $files->buildSpreadsheet('Machine Logs', array_values(AttendanceTransferService::MACHINE_LOG_COLUMNS), $rows),
            "machine-logs-{$stamp}.xlsx"
        );
    }

    public function importTemplate(AttendanceTransferService $files)
    {
        $sample = [
            ['1001', now()->subDay()->format('Y-m-d') . ' 08:55:12', 'ZK-001', '0', 'fingerprint', 'manual', '0'],
            ['1001', now()->subDay()->format('Y-m-d') . ' 18:05:40', 'ZK-001', '1', 'fingerprint', 'manual', '0'],
        ];
        $notes = [
            'Machine log import — one row per punch.',
            'Required: Employee ID (same ID as on the machine / HR employee list) and Log Time.',
            'Log Time formats: 2026-09-01 08:55:12, 01/09/2026 08:55, 01-09-2026 8:55 AM, or an Excel date-time cell.',
            'You may also use separate "Date" and "Time" columns instead of "Log Time".',
            'Optional: Device SN, Type Code, Verify Method, Source, Work Code.',
            'A punch already in the system (same Employee ID + Log Time) is skipped, so re-uploading the same file is safe.',
            'Punches are applied to attendance using each employee\'s shift windows (first in-window punch = In, last out-window punch = Out).',
        ];

        if (!AttendanceTransferService::spreadsheetAvailable()) {
            return $files->downloadDelimited('machine-log-import-template.csv', array_values(AttendanceTransferService::MACHINE_LOG_COLUMNS), function ($write) use ($sample) {
                array_map($write, $sample);
            });
        }

        return $files->downloadSpreadsheet(
            $files->buildSpreadsheet('Machine Logs', array_values(AttendanceTransferService::MACHINE_LOG_COLUMNS), $sample, $notes),
            'machine-log-import-template.xlsx'
        );
    }

    /**
     * Triggered by the "Sync" button on the Machine Attendance Log screen —
     * pulls any raw punch logs from the legacy database forward, exactly as
     * `php artisan hr:sync-legacy-data --only=machine-logs` does.
     */
    public function syncLegacy(): RedirectResponse
    {
        if (!config('database.connections.legacy.database')) {
            return back()->with('error', 'The legacy database connection is not configured.');
        }

        $before = HrAttendanceMachineLog::count();

        $exitCode = Artisan::call('hr:sync-legacy-data', ['--only' => 'machine-logs']);

        if ($exitCode !== 0) {
            return back()->with('error', 'Sync failed — check the application log for details.');
        }

        $inserted = HrAttendanceMachineLog::count() - $before;

        // Every log in the table (not just the newly-pulled ones) is replayed
        // through the exact same applyPunchToAttendance() the live ZKTeco push
        // uses, so hr_attendances stays in sync however a log got here. Already-
        // applied punches are no-ops (the shift-window/min-in/max-out logic only
        // changes a row when a punch actually moves it), so this is safe to run
        // on every click.
        $applied = $this->processMachineLogsIntoAttendance();

        $messageParts = [];
        $messageParts[] = $inserted > 0
            ? "pulled {$inserted} new machine log(s)"
            : 'no new machine logs to pull';
        $messageParts[] = $applied['updated'] > 0
            ? "updated attendance for {$applied['updated']} punch(es)"
            : 'no attendance changes needed';
        if ($applied['unmatched'] > 0) {
            $messageParts[] = "{$applied['unmatched']} log(s) skipped (employee_id not found)";
        }

        return back()->with('success', ucfirst(implode('; ', $messageParts)) . '.');
    }

    /**
     * Replays every stored machine log through applyPunchToAttendance() so
     * hr_attendances reflects them — used by the "Sync" button so legacy-
     * imported logs (which never went through receiveData()/receiveBulkData())
     * actually create/update attendance rows, not just sit in the log table.
     *
     * @return array{updated: int, unmatched: int}
     */
    private function processMachineLogsIntoAttendance(): array
    {
        $stats = ['updated' => 0, 'unmatched' => 0];

        $employees = HrEmployee::with(['shift', 'shiftRule.altShift'])->get()->keyBy('employee_id');

        HrAttendanceMachineLog::orderBy('id')
            ->chunkById(1000, function ($logs) use ($employees, &$stats) {
                foreach ($logs as $log) {
                    $employee = $employees->get($log->employee_id);
                    if (!$employee) {
                        $stats['unmatched']++;
                        continue;
                    }

                    $time = Carbon::parse($log->log_time, 'Asia/Dhaka');
                    $shift = $employee->resolveShiftForDate($time->toDateString())
                        ?? $this->resolveShiftFromRoster($employee, $time->toDateString());

                    if ($this->applyPunchToAttendance($employee, $time, $shift, $log->device_sn, $log->type_name)) {
                        $stats['updated']++;
                    }
                }
            });

        return $stats;
    }

    public function receiveAdmsRecords(Request $request): JsonResponse
    {
        try {
            $records = $request->input('records');

            if (!is_array($records) || empty($records)) {
                return response()->json(['status' => 'error', 'message' => 'No records provided.'], 400);
            }

            $loggedCount     = 0;
            $attendanceCount = 0;
            $skipped         = 0;

            foreach ($records as $record) {
                $employeeId = (string) ($record['employeeId'] ?? '');
                $timeStr    = $record['time'] ?? null;

                // Every record goes to the log regardless of validity
                if ($employeeId && $timeStr) {
                    $this->saveMachineLogAdms($record);
                    $loggedCount++;
                } else {
                    $skipped++;
                    continue;
                }

                // Try to process attendance if employee exists
                if ($this->processAdmsAttendance($record)) {
                    $attendanceCount++;
                }
            }

            Log::info('ADMS bulk received', [
                'total'      => count($records),
                'logged'     => $loggedCount,
                'attendance' => $attendanceCount,
                'skipped'    => $skipped,
            ]);

            return response()->json([
                'status'           => 'success',
                'message'          => 'ADMS records processed.',
                'total'            => count($records),
                'logged'           => $loggedCount,
                'attendance_synced'=> $attendanceCount,
                'skipped'          => $skipped,
            ]);

        } catch (\Throwable $e) {
            Log::error('receiveAdmsRecords error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function saveMachineLogAdms(array $record): void
    {
        try {
            HrAttendanceMachineLog::create([
                'device_sn'   => $record['deviceId']     ?? null,
                'employee_id' => $record['employeeId']   ?? null,
                'log_time'    => $record['time']          ?? null,
                'type_code'   => $record['status']        ?? null,
                'type_name'   => $record['verifyMethod']  ?? null,
                'source'      => $record['source']        ?? null,
                'external_id' => isset($record['id']) ? (string) $record['id'] : null,
                'work_code'   => $record['workCode']      ?? null,
                'received_at' => isset($record['receivedAt'])
                    ? Carbon::parse($record['receivedAt'])->toDateTimeString()
                    : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('saveMachineLogAdms failed: ' . $e->getMessage(), ['record' => $record]);
        }
    }

    private function processAdmsAttendance(array $record): bool
    {
        try {
            $employeeId = (string) ($record['employeeId'] ?? '');
            $timeStr    = $record['time'] ?? null;

            if (!$employeeId || !$timeStr) {
                return false;
            }

            $employee = HrEmployee::with(['shift', 'shiftRule.altShift'])->where('employee_id', $employeeId)->first();

            if (!$employee) {
                Log::info("ADMS: employee_id=$employeeId not found in users, log saved only.");
                return false;
            }

            $time  = Carbon::parse($timeStr, 'Asia/Dhaka');
            $shift = $employee->resolveShiftForDate($time->toDateString());

            // Resolve active shift from roster if employee has no default/rule shift
            if (!$shift) {
                $shift = $this->resolveShiftFromRoster($employee, $time->toDateString());
            }

            return $this->applyPunchToAttendance(
                $employee,
                $time,
                $shift,
                $record['deviceId'] ?? null,
                $record['verifyMethod'] ?? null
            );

        } catch (\Throwable $e) {
            Log::error('processAdmsAttendance failed: ' . $e->getMessage(), ['record' => $record]);
            return false;
        }
    }

    /**
     * Try to find the employee's shift from the shift roster for a given date.
     * Returns null if no roster entry exists (falls back to no-shift logic).
     */
    private function resolveShiftFromRoster(HrEmployee $employee, string $date): ?HrShift
    {
        // Check if HrShiftRosterEmployee model exists
        if (!class_exists(\ME\Hr\Models\HrShiftRosterEmployee::class)) {
            return null;
        }

        $rosterEntry = \ME\Hr\Models\HrShiftRosterEmployee::where('employee_id', $employee->id)
            ->whereDate('roster_date', $date)
            ->first();

        if (!$rosterEntry || !$rosterEntry->shift_id) {
            return null;
        }

        return HrShift::find($rosterEntry->shift_id);
    }

    public function import()
    {
        return view('hr::attendances.logImport');
    }

    /**
     * Upload a machine export (.dat/.txt from the device, or .csv/.xlsx — including a
     * file downloaded from this screen's Export). Every valid punch is stored in
     * hr_attendance_machine_logs (skipping ones already there) and, when the employee
     * is found, applied to attendance through the same shift-window logic as the live
     * machine push. Reports exactly what was imported, skipped and why.
     */
    public function importAction(Request $request, AttendanceTransferService $files)
    {
        $request->validate([
            'attendance_file' => 'required|file|mimes:txt,csv,dat,xlsx,xls|max:20480',
        ], [
            'attendance_file.mimes' => 'Only .dat, .txt, .csv, .xlsx or .xls files can be imported.',
        ]);

        @set_time_limit(900);
        @ini_set('memory_limit', '1024M');

        $rows = $files->readRows($request->file('attendance_file'));
        $header = $files->extractHeaderMap($rows, ['employee_id', 'log_time', 'date', 'device_sn', 'type_code', 'type_name', 'source', 'work_code']);

        $employees = HrEmployee::with(['shift', 'shiftRule.altShift'])->get()->keyBy('employee_id');
        $stats = ['total' => 0, 'imported' => 0, 'duplicate' => 0, 'invalid' => 0, 'unmatched' => 0, 'attendance' => 0];
        $problems = [];
        $seen = [];

        foreach ($rows as $row) {
            $stats['total']++;
            $punch = $this->parseImportedPunch($row['cells'], $header, $files);

            if (!$punch['employee_id'] || !$punch['log_time']) {
                $stats['invalid']++;
                $problems[] = [$row['line'], $punch['employee_id'] ?: '-', $punch['employee_id'] ? 'Date/time could not be read' : 'Employee ID is missing'];
                continue;
            }

            $key = $punch['employee_id'] . '|' . $punch['log_time'];
            if (isset($seen[$key]) || HrAttendanceMachineLog::where('employee_id', $punch['employee_id'])->where('log_time', $punch['log_time'])->exists()) {
                $stats['duplicate']++;
                $seen[$key] = true;
                continue;
            }
            $seen[$key] = true;

            HrAttendanceMachineLog::create([
                'device_sn'   => $punch['device_sn'],
                'employee_id' => $punch['employee_id'],
                'log_time'    => $punch['log_time'],
                'type_code'   => $punch['type_code'],
                'type_name'   => $punch['type_name'] ?: 'Manual_Import',
                'source'      => $punch['source'] ?: 'file_import',
                'work_code'   => $punch['work_code'],
                'received_at' => now(),
                'created_by'  => auth()->id(),
            ]);
            $stats['imported']++;

            $employee = $employees->get($punch['employee_id']);
            if (!$employee) {
                $stats['unmatched']++;
                $problems[] = [$row['line'], $punch['employee_id'], 'Log saved, but no employee has this ID — attendance not updated'];
                continue;
            }

            try {
                $time = Carbon::parse($punch['log_time'], 'Asia/Dhaka');
                $shift = $employee->resolveShiftForDate($time->toDateString())
                    ?? $this->resolveShiftFromRoster($employee, $time->toDateString());

                if ($this->applyPunchToAttendance($employee, $time, $shift, $punch['device_sn'], $punch['type_name'] ?: 'Manual_Import')) {
                    $stats['attendance']++;
                }
            } catch (\Throwable $e) {
                Log::error('Machine log import: attendance update failed', ['punch' => $punch, 'error' => $e->getMessage()]);
                $problems[] = [$row['line'], $punch['employee_id'], 'Log saved, but attendance update failed: ' . $e->getMessage()];
            }
        }

        $result = [
            'title' => 'Machine log import finished',
            'stats' => [
                'Rows in file'             => $stats['total'],
                'New punches saved'        => $stats['imported'],
                'Already existed (skipped)' => $stats['duplicate'],
                'Invalid rows'             => $stats['invalid'],
                'Employee not found'       => $stats['unmatched'],
                'Attendance days updated'  => $stats['attendance'],
            ],
            'problems' => array_slice($problems, 0, 200),
            'more'     => max(0, count($problems) - 200),
        ];

        $flash = $stats['imported'] > 0 ? 'success' : 'error';
        $message = $stats['imported'] > 0
            ? "{$stats['imported']} new punch(es) imported, attendance updated for {$stats['attendance']} punch(es)."
            : ($stats['total'] === 0 ? 'The file has no rows to import.' : 'No new punches were imported — see the details below.');

        return redirect()->route('hr-center.importZkteco')->with($flash, $message)->with('import_result', $result);
    }

    /**
     * Header-based files map by column name; headerless ZKTeco .dat/.txt keep the
     * original positional layout: ID, date, time, device SN, type code.
     */
    private function parseImportedPunch(array $cells, ?array $header, AttendanceTransferService $files): array
    {
        $get = fn (string $field) => isset($header[$field]) ? trim((string) ($cells[$header[$field]] ?? '')) : '';

        if ($header) {
            // Separate "Date" + "Time" columns ("Time" lands in log_time via the header map),
            // or a single combined date-time column under either name.
            $logTime = null;
            if ($get('date') !== '' && $get('log_time') !== '') {
                $logTime = $files->normalizeDateTime($get('date'), $get('log_time'));
            }
            $logTime ??= $files->normalizeDateTime($get('log_time')) ?? $files->normalizeDateTime($get('date'));

            return [
                'employee_id' => $get('employee_id'),
                'log_time'    => $logTime,
                'device_sn'   => $get('device_sn') ?: null,
                'type_code'   => $get('type_code') ?: null,
                'type_name'   => $get('type_name') ?: null,
                'source'      => $get('source') ?: null,
                'work_code'   => $get('work_code') ?: null,
            ];
        }

        $cells = array_values(array_filter($cells, fn ($c) => $c !== ''));
        $employeeId = $cells[0] ?? '';
        $logTime = null;
        $next = 2;

        if (isset($cells[1])) {
            $logTime = isset($cells[2]) ? $files->normalizeDateTime($cells[1], $cells[2]) : null;
            if ($logTime) {
                $next = 3;
            } else {
                $logTime = $files->normalizeDateTime($cells[1]);
            }
            // "01/09/2026 08:55 AM" splits into three parts
            if ($logTime && isset($cells[$next]) && in_array(strtoupper($cells[$next]), ['AM', 'PM'], true)) {
                $logTime = $files->normalizeDateTime($cells[1], $cells[2] . ' ' . $cells[$next]);
                $next++;
            }
        }

        return [
            'employee_id' => $employeeId,
            'log_time'    => $logTime,
            'device_sn'   => $cells[$next] ?? null,
            'type_code'   => $cells[$next + 1] ?? null,
            'type_name'   => null,
            'source'      => null,
            'work_code'   => null,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function saveMachineLog(string $userId, string $timestamp, ?string $sn, ?string $verifyType, ?string $typeCode = null): void
    {
        try {
            HrAttendanceMachineLog::create([
                'device_sn'   => $sn,
                'employee_id' => $userId,
                'log_time'    => $timestamp,
                'type_name'   => $verifyType,
                'type_code'   => $typeCode,
            ]);
        } catch (\Throwable $e) {
            Log::error("saveMachineLog failed for $userId: " . $e->getMessage());
        }
    }

    private function saveAttendance(string $userId, string $timestamp, ?string $sn, ?string $verifyType): void
    {
        try {
            $employee = HrEmployee::with('shift')->where('employee_id', $userId)->first();

            if (!$employee) {
                Log::warning("Attendance skipped: employee_id=$userId not found. SN=$sn");
                return;
            }

            $time  = Carbon::parse($timestamp, 'Asia/Dhaka');
            $shift = $employee->shift;

            $this->applyPunchToAttendance($employee, $time, $shift, $sn, $verifyType);

        } catch (\Throwable $e) {
            Log::error("saveAttendance failed for $userId: " . $e->getMessage());
        }
    }

    /**
     * Apply a single punch to the employee's attendance record according to the shift's
     * configured windows:
     *   - a punch inside [start_allow_time, out_time_start)  → in_time (first punch in the
     *     window wins; later punches in the same window never move it)
     *   - a punch inside [out_time_start, next day's start_allow_time)  → out_time (last punch
     *     in the window wins)
     *     (a punch after midnight but before the next day's in-window belongs to the
     *     previous day's attendance, so overnight shifts are handled correctly)
     *   - a punch outside both windows is ignored entirely — it never touches in_time/out_time
     * late_allow_time does not affect these window boundaries — it is only the Present/Late
     * cutoff applied to the resulting in_time (see resolveStatus()).
     * If the shift has start_allow_time/out_time_start unset, falls back to the legacy
     * earliest-punch-in / latest-punch-out behaviour for that punch's calendar date.
     *
     * Returns true if the punch produced an attendance change, false if it was ignored.
     */
    private function applyPunchToAttendance(HrEmployee $employee, Carbon $time, ?HrShift $shift, ?string $deviceSn, ?string $verifyType): bool
    {
        $window = $this->resolvePunchWindow($shift, $time);

        if (!$window) {
            return false;
        }

        [$type, $attendanceDate] = $window;

        $attendance = HrAttendance::where('employee_id', $employee->id)
            ->whereDate('date', $attendanceDate)
            ->first();

        // A locked day is immutable — a late/re-synced machine punch must not silently
        // overwrite it either, same rule as manual edits (AttendanceController).
        $day = Carbon::parse($attendanceDate);
        if (($attendance && $attendance->is_locked)
            || HrLock::isLocked('attendance', $day->year, $day->month, $employee->department_id)
        ) {
            Log::info("Attendance punch ignored (locked): employee={$employee->employee_id} date={$attendanceDate}");
            return false;
        }

        // A "Day Swap" (see AttendanceReplaceOffController) already moved everyone's
        // attendance off this date onto its replace_date and is relying on this date
        // staying empty — a machine re-sync must not silently resurrect attendance
        // (and therefore salary/OT) on the original weekend/holiday date.
        if (HrAttendanceReplaceOff::active()->where('worked_date', $attendanceDate)->exists()) {
            Log::info("Attendance punch ignored (day-swapped): employee={$employee->employee_id} date={$attendanceDate}");
            return false;
        }

        if (!$attendance) {
            $attendance              = new HrAttendance();
            $attendance->employee_id = $employee->id;
            $attendance->date        = $attendanceDate;
            $attendance->device_sn   = $deviceSn;
            $attendance->via         = 'machine';
            $attendance->verify_type = $verifyType;
        }

        $currentIn  = $this->toCarbon($attendance->date, $attendance->in_time);
        $currentOut = $this->toCarbon($attendance->date, $attendance->out_time);

        if ($type === 'in' || $type === 'legacy') {
            if (!$currentIn || $time->lt($currentIn)) {
                $attendance->in_time = $time->format('H:i:s');
                $currentIn           = $time;
            }
        }

        if ($type === 'out' || ($type === 'legacy' && $currentIn && $time->gt($currentIn))) {
            if (!$currentOut || $time->gt($currentOut)) {
                $attendance->out_time = $time->format('H:i:s');
                $currentOut           = $time;
            }
        }

        $attendance->status = $this->resolveStatus($shift, $currentIn);

        if ($currentIn && $currentOut) {
            // out_time is only ever stored as a time-of-day; if it looks earlier than in_time
            // it's really an overnight punch on the following calendar day.
            $outForCalc = $currentOut->lt($currentIn) ? $currentOut->copy()->addDay() : $currentOut;

            $attendance->total_working_minute = (int) $currentIn->diffInMinutes($outForCalc);
            $attendance->total_ot_minute      = $this->resolveOvertimeMinutes($shift, $currentIn, $outForCalc, $employee);
        }

        $attendance->save();

        Log::info("Attendance synced: employee={$employee->employee_id} date={$attendance->date} type=$type status={$attendance->status}");

        return true;
    }

    /**
     * Classify a punch against the shift's configured windows.
     * Returns [type, attendanceDate] where type is 'in' | 'out' | 'legacy',
     * or null when the punch falls outside all windows and must be ignored.
     */
    private function resolvePunchWindow(?HrShift $shift, Carbon $time): ?array
    {
        $punchDate = $time->toDateString();

        $inStart  = $shift?->start_allow_time;
        $outStart = $shift?->out_time_start;

        // Shift windows not fully configured — fall back to legacy earliest-in/latest-out behaviour.
        if (!$shift || !$inStart || !$outStart) {
            return ['legacy', $punchDate];
        }

        $inWinStart  = Carbon::parse($punchDate . ' ' . $inStart, 'Asia/Dhaka');
        $outWinStart = Carbon::parse($punchDate . ' ' . $outStart, 'Asia/Dhaka');
        // The in-window runs right up to (but excludes) out_time_start, so a punch at
        // exactly out_time_start is always treated as an out-punch, never in.
        $inWinEnd    = $outWinStart->copy()->subSecond();

        if ($time->between($inWinStart, $inWinEnd)) {
            return ['in', $punchDate];
        }

        $dayEnd = Carbon::parse($punchDate . ' 23:59:59', 'Asia/Dhaka');

        if ($time->between($outWinStart, $dayEnd)) {
            return ['out', $punchDate];
        }

        // Early-morning continuation of the previous day's out-window, up to (but excluding)
        // today's in-window start — belongs to yesterday's attendance record.
        $dayStart = Carbon::parse($punchDate . ' 00:00:00', 'Asia/Dhaka');
        if ($time->between($dayStart, $inWinStart->copy()->subSecond())) {
            return ['out', $time->copy()->subDay()->toDateString()];
        }

        return null;
    }

    private function resolveStatus(?HrShift $shift, ?Carbon $inTime): string
    {
        if (!$inTime || !$shift || !$shift->start_time) {
            return 'Present';
        }

        // late_allow_time is the absolute threshold time (e.g. 09:15:00); use it directly if set
        $lateThreshold = $shift->late_allow_time
            ? Carbon::parse($inTime->toDateString() . ' ' . $shift->late_allow_time, 'Asia/Dhaka')
            : Carbon::parse($inTime->toDateString() . ' ' . $shift->start_time, 'Asia/Dhaka');

        return $inTime->gt($lateThreshold) ? 'Late' : 'Present';
    }

    private function resolveOvertimeMinutes(?HrShift $shift, Carbon $inTime, Carbon $outTime, ?HrEmployee $employee = null): int
    {
        if (!$shift || !$shift->end_time) {
            return 0;
        }

        $shiftEnd = Carbon::parse($inTime->toDateString() . ' ' . $shift->end_time, 'Asia/Dhaka');

        if ($outTime->lte($shiftEnd)) {
            return 0;
        }

        // Cap OT at out_time_start if defined — but only when it's actually a sensible
        // cap (after shift end). A misconfigured shift with out_time_start earlier than
        // end_time would otherwise clamp $effectiveOut backward past $shiftEnd, turning
        // a positive OT span into a large negative one.
        $otCap = ($shift->out_time_start && Carbon::parse($inTime->toDateString() . ' ' . $shift->out_time_start, 'Asia/Dhaka')->gt($shiftEnd))
            ? Carbon::parse($inTime->toDateString() . ' ' . $shift->out_time_start, 'Asia/Dhaka')
            : $outTime;

        $effectiveOut = $outTime->lt($otCap) ? $outTime : $otCap;

        // OT only starts counting once the employee has worked past shift end by more
        // than the configured grace period — e.g. a 30-minute grace means someone who
        // leaves 20 minutes late shows 0 OT, and someone who leaves 40 minutes late
        // shows only 10 (the excess beyond the grace window, not the full 40).
        $graceMinutes = EmployeeAttendanceService::resolveOtGraceMinutes($employee);
        $minutesPastShiftEnd = (int) $shiftEnd->diffInMinutes($effectiveOut);
        $minutesPastGrace = max(0, $minutesPastShiftEnd - $graceMinutes);

        return EmployeeAttendanceService::applyMinimumOtBucketing($minutesPastGrace);
    }

    private function toCarbon(?string $date, $timeValue): ?Carbon
    {
        if (!$timeValue) {
            return null;
        }

        $baseDate = $date ?: Carbon::today('Asia/Dhaka')->toDateString();
        $timePart = $timeValue instanceof Carbon
            ? $timeValue->format('H:i:s')
            : Carbon::parse($timeValue, 'Asia/Dhaka')->format('H:i:s');

        return Carbon::parse($baseDate . ' ' . $timePart, 'Asia/Dhaka');
    }
}
