<?php

namespace ME\Hr\Http\Controllers\Portal;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ME\Hr\Http\Controllers\AttendanceController;
use ME\Hr\Models\HrAttendance;
use ME\Hr\Services\EmployeeAttendanceService;

class PortalAttendanceController extends PortalController
{
    public function index(Request $request)
    {
        $employee = $this->employee();

        $year = (int) ($request->query('year') ?: now()->year);
        $month = (int) ($request->query('month') ?: now()->month);

        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $today = now()->startOfDay();
        $lastDay = $periodEnd->gt($today) ? $today : $periodEnd;

        // Reuse the same engine the admin daily-attendance/job-card reports run on
        // so status (Present/Late/Absent/Leave/Holiday/Weekend/Punch Missing/...),
        // shift, and remarks stay identical between the employee portal and HR side.
        $attendancePack = EmployeeAttendanceService::getEmployeeAttendanceByDate(
            $employee->id,
            $periodStart->format('Y-m-d'),
            $lastDay->format('Y-m-d')
        );

        $reportRows = collect($attendancePack['attendance'])
            ->reject(fn ($row) => $row['status_key'] === 'not_employed')
            ->map(function ($row) {
                $row['date_carbon'] = Carbon::createFromFormat('d-m-Y', $row['date']);

                return $row;
            })
            ->sortByDesc(fn ($row) => $row['date_carbon']->format('Y-m-d'))
            ->values();

        $summary = [
            'present' => $reportRows->whereIn('status_key', ['present', 'late'])->count(),
            'late' => $reportRows->where('status_key', 'late')->count(),
            'absent' => $reportRows->where('status_key', 'absent')->count(),
            'leave_weekend_holiday' => $reportRows->whereIn('status_key', ['weekend', 'holiday'])->count(),
        ];

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::create(2000, $m, 1)->format('F');
        }
        $years = range(now()->year, now()->year - 3);

        return view('hr::portal.attendance.index', [
            'employee' => $employee,
            'rows' => $reportRows,
            'summary' => $summary,
            'months' => $months,
            'years' => $years,
            'selectedMonth' => $month,
            'selectedYear' => $year,
        ]);
    }

    public function punch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
        ]);

        $employee = $this->employee();
        $today = now()->format('Y-m-d');
        $shift = $employee->resolveShiftForDate($today);

        $attendance = HrAttendance::firstOrNew([
            'employee_id' => $employee->id,
            'date' => $today,
        ]);

        if (!$attendance->exists || !$attendance->in_time) {
            $attendance->in_time = now()->format('H:i:s');
            $attendance->latitude = $validated['latitude'];
            $attendance->longitude = $validated['longitude'];
            $attendance->in_address = $validated['address'] ?? null;
            $attendance->via = 'employee-portal';
            $attendance->verify_type = 'geo';
            $attendance->status = AttendanceController::calculateStatus($attendance, $shift);
            $attendance->save();

            return redirect()->route('employee-portal.dashboard')->with('success', 'Punched in successfully.');
        }

        if (!$attendance->out_time) {
            $attendance->out_time = now()->format('H:i:s');
            $attendance->out_latitude = $validated['latitude'];
            $attendance->out_longitude = $validated['longitude'];
            $attendance->out_address = $validated['address'] ?? null;
            $attendance->status = AttendanceController::calculateStatus($attendance, $shift);

            // Same OT/worked-duration derivation as the admin edit screen
            // (AttendanceController::update()), so a self-punched day reports
            // identically to a manually-entered one everywhere downstream.
            $inTime = Carbon::parse($attendance->in_time);
            $outTime = Carbon::parse($attendance->out_time);
            if ($outTime->lt($inTime)) {
                $outTime->addDay();
            }
            $attendance->total_working_minute = (int) $inTime->diffInMinutes($outTime);

            if ($shift) {
                $shiftEnd = Carbon::parse($shift->end_time);
                if ($outTime->gt($shiftEnd)) {
                    $graceMinutes = EmployeeAttendanceService::resolveOtGraceMinutes($employee);
                    $minutesPastGrace = max(0, (int) $shiftEnd->diffInMinutes($outTime) - $graceMinutes);
                    $attendance->total_ot_minute = EmployeeAttendanceService::applyMinimumOtBucketing($minutesPastGrace);
                } else {
                    $attendance->total_ot_minute = 0;
                }
            } else {
                $attendance->total_ot_minute = 0;
            }

            $attendance->save();

            return redirect()->route('employee-portal.dashboard')->with('success', 'Punched out successfully.');
        }

        return redirect()->route('employee-portal.dashboard')->with('error', 'You have already punched in and out for today.');
    }
}
