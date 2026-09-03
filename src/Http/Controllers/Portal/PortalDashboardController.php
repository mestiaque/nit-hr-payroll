<?php

namespace ME\Hr\Http\Controllers\Portal;

use Carbon\Carbon;
use ME\Hr\Models\HrAttendance;
use ME\Hr\Models\HrConveyanceRequest;
use ME\Hr\Models\HrEmployeeLeave;
use ME\Hr\Models\HrLeaveInfo;
use ME\Hr\Models\HrNotice;

class PortalDashboardController extends PortalController
{
    public function index()
    {
        $employee = $this->employee();

        $today = HrAttendance::where('employee_id', $employee->id)
            ->where('date', now()->format('Y-m-d'))
            ->first();

        $monthRows = HrAttendance::where('employee_id', $employee->id)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->get();

        $presentDays = $monthRows->filter(fn ($row) => $row->in_time)->count();

        $lateDays = $monthRows->filter(function ($row) use ($employee) {
            if (!$row->in_time) {
                return false;
            }
            $shift = $employee->resolveShiftForDate($row->date);
            $threshold = $shift?->late_allow_time ?? $shift?->start_time;
            if (!$threshold) {
                return false;
            }
            return Carbon::parse($row->date . ' ' . $row->in_time)->gt(Carbon::parse($row->date . ' ' . $threshold));
        })->count();

        $leaveDaysThisMonth = HrEmployeeLeave::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('leave_from', now()->year)
            ->whereMonth('leave_from', now()->month)
            ->sum('total_days');

        $leaveTypes = HrLeaveInfo::where('status', 'active')->orderBy('name')->get();
        $takenByType = HrEmployeeLeave::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('leave_from', now()->year)
            ->get()
            ->groupBy('leave_type_id')
            ->map(fn ($rows) => (float) $rows->sum('total_days'));

        $leaveSummary = $leaveTypes->map(function ($type) use ($takenByType) {
            $taken = $takenByType->get($type->id, 0);
            $allocated = max(1, (float) $type->days);
            return [
                'name' => $type->name,
                'taken_display' => $this->trimDecimal($taken),
                'allocated' => (int) $type->days,
                'percent' => min(100, round(($taken / $allocated) * 100)),
            ];
        });

        $recentConveyance = HrConveyanceRequest::where('employee_id', $employee->id)
            ->latest('id')
            ->limit(3)
            ->get();

        $weekend = strtolower($employee->weekend ?? 'friday');
        $weekStrip = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->copy()->subDays($i)->startOfDay();
            $dateKey = $date->format('Y-m-d');
            $row = $monthRows->firstWhere(fn ($r) => Carbon::parse($r->date)->format('Y-m-d') === $dateKey)
                ?? HrAttendance::where('employee_id', $employee->id)->where('date', $dateKey)->first();

            $hasIn = $row && $row->in_time;
            $isWeekend = strtolower($date->format('l')) === $weekend;

            if ($date->isToday() && !$hasIn) {
                $status = 'today';
            } elseif ($hasIn) {
                $shift = $employee->resolveShiftForDate($date);
                $threshold = $shift?->late_allow_time ?? $shift?->start_time;
                $isLate = $threshold && Carbon::parse($dateKey . ' ' . $row->in_time)->gt(Carbon::parse($dateKey . ' ' . $threshold));
                $status = $isLate ? 'late' : 'present';
            } elseif ($isWeekend) {
                $status = 'weekend';
            } else {
                $status = 'absent';
            }

            $weekStrip->push(['label' => $date->format('D'), 'day' => $date->format('d'), 'status' => $status, 'is_today' => $date->isToday()]);
        }

        $stats = [
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'leave_days' => $this->trimDecimal((float) $leaveDaysThisMonth),
            'pending_leaves' => HrEmployeeLeave::where('employee_id', $employee->id)->where('status', 'pending')->count(),
            'pending_conveyance' => HrConveyanceRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
        ];

        $latestNotice = HrNotice::active()->orderByDesc('published_at')->first();

        return view('hr::portal.dashboard', [
            'employee' => $employee,
            'today' => $today,
            'stats' => $stats,
            'leaveSummary' => $leaveSummary,
            'recentConveyance' => $recentConveyance,
            'weekStrip' => $weekStrip,
            'latestNotice' => $latestNotice,
        ]);
    }

    private function trimDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.') ?: '0';
    }
}
