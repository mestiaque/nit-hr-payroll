<?php

namespace ME\Hr\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ME\Hr\Http\Controllers\AttendanceController;
use ME\Hr\Models\HrAttendance;
use ME\Hr\Models\HrAttendanceMachineLog;
use ME\Hr\Models\HrDepartment;
use ME\Hr\Models\HrDesignation;
use ME\Hr\Models\HrEmployee;
use ME\Hr\Models\HrEmployeeBasicInfo;
use ME\Hr\Models\HrMaritalStatus;
use ME\Hr\Models\HrReligion;
use ME\Hr\Models\HrSex;

/**
 * Pulls employee + attendance data forward from the legacy `payrole` database
 * (configured as the 'legacy' DB connection) into the current HR system.
 *
 * Safe to re-run: employees are matched by employee_id (never duplicated or
 * overwritten once matched), and attendance rows are only inserted for an
 * (employee, date) that doesn't already have a row — legacy data only fills
 * gaps, it never overwrites anything already in the new system.
 */
class SyncLegacyDataCommand extends Command
{
    protected $signature = 'hr:sync-legacy-data
        {--only= : Limit the run to "employees", "attendance", or "machine-logs" (default: all three)}
        {--dry-run : Run the whole sync and report the outcome, then roll everything back}';

    protected $description = 'Sync employee, attendance, and raw machine-punch-log data forward from the legacy payrole database into the current HR system.';

    private array $stats = [
        'employees_matched' => 0,
        'employees_created' => 0,
        'attendance_inserted' => 0,
        'attendance_skipped_existing' => 0,
        'attendance_skipped_orphaned' => 0,
        'attendance_overnight_corrected' => 0,
        'machine_logs_inserted' => 0,
        'machine_logs_skipped_existing' => 0,
    ];

    public function handle(): int
    {
        $only = $this->option('only');
        if ($only && !in_array($only, ['employees', 'attendance', 'machine-logs'], true)) {
            $this->error('--only must be "employees", "attendance", or "machine-logs".');
            return self::FAILURE;
        }

        if (!config('database.connections.legacy.database')) {
            $this->error('The "legacy" DB connection is not configured (DB_DATABASE_OLD is empty in .env). Aborting.');
            return self::FAILURE;
        }

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to the legacy database: ' . $e->getMessage());
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $employeeMap = (!$only || $only === 'employees')
                ? $this->syncEmployees()
                : HrEmployee::whereNotNull('employee_id')->pluck('id', 'employee_id')->all();

            if (!$only || $only === 'attendance') {
                $this->syncAttendance($employeeMap);
            }

            if (!$only || $only === 'machine-logs') {
                $this->syncMachineLogs();
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn('Dry run complete — nothing was written.');
            } else {
                DB::commit();
                $this->info('Sync complete.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Metric', 'Count'], collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }

    /**
     * @return array<string, int> legacy employee_id code => new hr_employees.id
     */
    private function syncEmployees(): array
    {
        $legacyUsers = DB::connection('legacy')->table('users')
            ->whereNotNull('employee_id')
            ->where('employee_id', '!=', '')
            ->get();

        $legacyInfoByUserId = DB::connection('legacy')->table('employee_info')->get()->keyBy('user_id');

        // Old system stores department/designation names in a shared "attributes"
        // table (type=3 department, type=2 designation) referenced by id from users.
        $legacyAttributeNames = DB::connection('legacy')->table('attributes')
            ->whereIn('type', [2, 3])
            ->pluck('name', 'id');

        $existingByEmployeeId = HrEmployee::whereIn('employee_id', $legacyUsers->pluck('employee_id'))
            ->pluck('id', 'employee_id');

        $departments = $this->nameLookup(HrDepartment::pluck('id', 'name'));
        $designations = $this->nameLookup(HrDesignation::pluck('id', 'name'));
        $sexes = $this->nameLookup(HrSex::pluck('id', 'name'));
        $religions = $this->nameLookup(HrReligion::pluck('id', 'name'));
        $maritalStatuses = $this->nameLookup(HrMaritalStatus::pluck('id', 'name'));

        $map = [];

        foreach ($legacyUsers as $old) {
            if (isset($existingByEmployeeId[$old->employee_id])) {
                $map[$old->employee_id] = $existingByEmployeeId[$old->employee_id];
                $this->stats['employees_matched']++;
                continue;
            }

            $info = $legacyInfoByUserId->get($old->id);

            $departmentName = $old->department_id ? ($legacyAttributeNames[$old->department_id] ?? null) : null;
            $designationName = $old->designation_id ? ($legacyAttributeNames[$old->designation_id] ?? null) : null;
            $sexName = $info->gender ?? $old->gender ?? null;
            $religionName = $info->religion ?? $old->religion ?? null;
            $maritalName = $info->marital_status ?? $old->marital_status ?? null;

            $employee = HrEmployee::create([
                'name' => $old->name ?: $old->employee_id,
                'bn_name' => $old->bn_name,
                'employee_id' => $old->employee_id,
                'join_date' => $old->joining_date,
                'department_id' => $departmentName ? ($departments[$this->normalize($departmentName)] ?? null) : null,
                'designation_id' => $designationName ? ($designations[$this->normalize($designationName)] ?? null) : null,
                'personal_contact' => $old->mobile,
                'emergency_contact' => $old->emergency_mobile,
                'employment_status' => $old->employment_status,
                'exited_at' => $old->exited_at ? Carbon::parse($old->exited_at)->format('Y-m-d') : null,
                'status' => ($old->employee_status ?? 'active') === 'active' ? 1 : 0,
            ]);

            $map[$old->employee_id] = $employee->id;
            $this->stats['employees_created']++;

            $basic = [
                'father_name' => $info->father_name ?? $old->father_name,
                'mother_name' => $info->mother_name ?? $old->mother_name,
                'spouse_name' => $info->spouse_name ?? $old->spouse_name,
                'birth_date' => $info->date_of_birth ?? $old->dob,
                'blood_group' => $info->blood_group ?? $old->blood_group,
                'national_id_no' => $info->nid ?? $old->nid_number,
                'birth_registration_no' => $info->birth_certificate ?? $old->birth_registration,
                'passport_no' => $old->passport_no,
                'driving_license_no' => $old->driving_license,
                'educational_experience' => $old->education,
                'sex_id' => $sexName ? ($sexes[$this->normalize($sexName)] ?? null) : null,
                'religion_id' => $religionName ? ($religions[$this->normalize($religionName)] ?? null) : null,
                'marital_status_id' => $maritalName ? ($maritalStatuses[$this->normalize($maritalName)] ?? null) : null,
            ];

            if (array_filter($basic, fn ($v) => !is_null($v) && $v !== '')) {
                $basic['employee_id'] = $employee->id;
                HrEmployeeBasicInfo::create($basic);
            }
        }

        return $map;
    }

    /**
     * @param array<string, int> $employeeMap legacy employee_id code => new hr_employees.id
     */
    private function syncAttendance(array $employeeMap): void
    {
        if (empty($employeeMap)) {
            return;
        }

        // Map legacy users.id -> new hr_employees.id, restricted to employee_id codes
        // we actually resolved above. Any attendance row whose user_id isn't in this
        // list (deleted user, bad reference, etc.) is an orphan and gets skipped.
        $legacyUserIdToEmployeeId = [];
        $legacyUsersForMap = DB::connection('legacy')->table('users')
            ->whereIn('employee_id', array_keys($employeeMap))
            ->select('id', 'employee_id')
            ->get();
        foreach ($legacyUsersForMap as $u) {
            $legacyUserIdToEmployeeId[$u->id] = $employeeMap[$u->employee_id];
        }

        if (empty($legacyUserIdToEmployeeId)) {
            return;
        }

        $rows = DB::connection('legacy')->table('attendances')
            ->orderBy('id')
            ->get();

        // Consolidate the day's raw punches (machines/web often log several rows
        // per employee per day) into a single first-in / last-out row per date.
        $grouped = [];

        foreach ($rows as $row) {
            $newEmployeeId = $legacyUserIdToEmployeeId[$row->user_id] ?? null;
            if (!$newEmployeeId) {
                $this->stats['attendance_skipped_orphaned']++;
                continue;
            }

            if (!$row->in_time && !$row->out_time) {
                continue;
            }

            $anchor = $row->in_time ?: $row->out_time;
            $date = $row->date ?: Carbon::parse($anchor)->format('Y-m-d');

            $inTime = $row->in_time ? Carbon::parse($row->in_time) : null;
            $outTime = $row->out_time ? Carbon::parse($row->out_time) : null;

            // A handful of legacy rows have out_time earlier than in_time — a genuine
            // overnight shift whose out punch never had its date rolled forward.
            if ($inTime && $outTime && $outTime->lt($inTime)) {
                $outTime->addDay();
                $this->stats['attendance_overnight_corrected']++;
            }

            $key = $newEmployeeId . '_' . $date;
            $grouped[$key] ??= [
                'employee_id' => $newEmployeeId,
                'date' => $date,
                'in_time' => null,
                'out_time' => null,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
            ];

            if ($inTime) {
                $t = $inTime->format('H:i:s');
                if (!$grouped[$key]['in_time'] || $t < $grouped[$key]['in_time']) {
                    $grouped[$key]['in_time'] = $t;
                    $grouped[$key]['latitude'] = $row->latitude;
                    $grouped[$key]['longitude'] = $row->longitude;
                }
            }

            if ($outTime) {
                $t = $outTime->format('H:i:s');
                if (!$grouped[$key]['out_time'] || $t > $grouped[$key]['out_time']) {
                    $grouped[$key]['out_time'] = $t;
                }
            }
        }

        if (empty($grouped)) {
            return;
        }

        $employeeIds = array_unique(array_column($grouped, 'employee_id'));

        $existing = HrAttendance::whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'date'])
            ->map(fn ($r) => $r->employee_id . '_' . Carbon::parse($r->date)->format('Y-m-d'))
            ->flip();

        $employees = HrEmployee::whereIn('id', $employeeIds)->get()->keyBy('id');

        foreach ($grouped as $key => $data) {
            if (isset($existing[$key])) {
                $this->stats['attendance_skipped_existing']++;
                continue;
            }

            $employee = $employees->get($data['employee_id']);
            $shift = $employee?->resolveShiftForDate($data['date']);

            $attendance = new HrAttendance([
                'employee_id' => $data['employee_id'],
                'date' => $data['date'],
                'in_time' => $data['in_time'],
                'out_time' => $data['out_time'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'via' => 'legacy-import',
                'verify_type' => 'legacy',
            ]);

            if ($data['in_time'] && $data['out_time']) {
                $in = Carbon::parse($data['date'] . ' ' . $data['in_time']);
                $out = Carbon::parse($data['date'] . ' ' . $data['out_time']);
                if ($out->lt($in)) {
                    $out->addDay();
                }
                $attendance->total_working_minute = (int) $in->diffInMinutes($out);
            }

            $attendance->status = AttendanceController::calculateStatus($attendance, $shift);
            $attendance->save();

            $this->stats['attendance_inserted']++;
        }
    }

    /**
     * Raw device punch logs are copied over as-is (they're audit-trail data, not
     * business records) using the same `hr_attendance_machine_logs` shape the
     * live ZKTeco ingestion (AttendanceMachineController) already writes to.
     * `employee_id` here is the employee_id CODE string, exactly as the legacy
     * table stores it — matches how live machine pushes are stored too.
     */
    private function syncMachineLogs(): void
    {
        // The live table enforces one row per (employee_id, log_time) — different
        // devices/re-imports sometimes log the exact same punch twice, so this set
        // is checked both against what's already stored and within this run itself.
        $seen = [];
        HrAttendanceMachineLog::select('id', 'employee_id', 'log_time')
            ->chunkById(2000, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    $seen[$row->employee_id . '|' . Carbon::parse($row->log_time)->format('Y-m-d H:i:s')] = true;
                }
            });

        DB::connection('legacy')->table('attendance_machine_logs')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$seen) {
                $batch = [];
                $now = now();

                foreach ($rows as $row) {
                    $key = $row->user_id . '|' . $row->log_time;
                    if (isset($seen[$key])) {
                        $this->stats['machine_logs_skipped_existing']++;
                        continue;
                    }
                    $seen[$key] = true;

                    $batch[] = [
                        'device_sn' => $row->device_sn,
                        'employee_id' => $row->user_id,
                        'log_time' => $row->log_time,
                        'type_code' => $row->type_code !== null ? (string) $row->type_code : null,
                        'type_name' => $row->type_name,
                        'source' => 'legacy-import',
                        'external_id' => 'legacy-' . $row->id,
                        'received_at' => $row->created_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($batch)) {
                    HrAttendanceMachineLog::insert($batch);
                    $this->stats['machine_logs_inserted'] += count($batch);
                }
            });
    }

    private function nameLookup($idsByName): array
    {
        $out = [];
        foreach ($idsByName as $name => $id) {
            $out[$this->normalize($name)] = $id;
        }
        return $out;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
