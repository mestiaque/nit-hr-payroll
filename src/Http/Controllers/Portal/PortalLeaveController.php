<?php

namespace ME\Hr\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ME\Hr\Models\HrEmployeeLeave;
use ME\Hr\Models\HrLeaveInfo;

class PortalLeaveController extends PortalController
{
    public function index()
    {
        $employee = $this->employee();

        $leaves = HrEmployeeLeave::where('employee_id', $employee->id)
            ->with('leaveType')
            ->latest('id')
            ->limit(50)
            ->get();

        $leaveTypes = HrLeaveInfo::where('status', 'active')->orderBy('name')->get();

        return view('hr::portal.leave.index', [
            'employee' => $employee,
            'leaves' => $leaves,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:hr_leave_infos,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $employee = $this->employee();

        $totalDays = \Carbon\Carbon::parse($validated['start_date'])->diffInDays(\Carbon\Carbon::parse($validated['end_date'])) + 1;

        $leave = HrEmployeeLeave::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'application_date' => now()->format('Y-m-d'),
            'leave_from' => $validated['start_date'],
            'leave_to' => $validated['end_date'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        if (class_exists(\App\Services\ApprovalService::class)) {
            app(\App\Services\ApprovalService::class)->request([
                'module' => 'hr.leave_request',
                'approvable' => $leave,
                'title' => "Leave Request - {$employee->name}",
                'description' => "{$employee->name} ({$employee->employee_id}) requested leave from {$validated['start_date']} to {$validated['end_date']}.",
                'route_name' => 'hr-center.employees.leaves.page',
                'route_params' => ['employee' => $employee->id],
            ]);
        }

        return redirect()->route('employee-portal.leave.index')->with('success', 'Leave request submitted for approval.');
    }
}
