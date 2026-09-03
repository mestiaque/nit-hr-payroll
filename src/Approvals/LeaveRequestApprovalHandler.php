<?php

namespace ME\Hr\Approvals;

use App\Approvals\BaseApprovalHandler;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestApprovalHandler extends BaseApprovalHandler
{
    public function recipients(?Model $approvable, Approval $approval): array
    {
        return User::whereHas('permission', function ($q) {
            $q->where('name', 'Admin');
        })->pluck('email')->filter()->all();
    }

    public function onApproved(Approval $approval): void
    {
        $leave = $approval->approvable;
        if ($leave && $leave->status !== 'approved') {
            $leave->status = 'approved';
            $leave->save();
        }
    }

    public function onRejected(Approval $approval): void
    {
        $leave = $approval->approvable;
        if ($leave && $leave->status !== 'rejected') {
            $leave->status = 'rejected';
            $leave->save();
        }
    }
}
