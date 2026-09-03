<?php

namespace ME\Hr\Approvals;

use App\Approvals\BaseApprovalHandler;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConveyanceRequestApprovalHandler extends BaseApprovalHandler
{
    public function recipients(?Model $approvable, Approval $approval): array
    {
        return User::whereHas('permission', function ($q) {
            $q->where('name', 'Admin');
        })->pluck('email')->filter()->all();
    }

    public function onApproved(Approval $approval): void
    {
        $conveyance = $approval->approvable;
        if ($conveyance && $conveyance->status !== 'approved') {
            $conveyance->status = 'approved';
            $conveyance->approved_by = $approval->approved_by;
            $conveyance->approved_at = $approval->approved_at;
            $conveyance->save();
        }
    }

    public function onRejected(Approval $approval): void
    {
        $conveyance = $approval->approvable;
        if ($conveyance && $conveyance->status !== 'rejected') {
            $conveyance->status = 'rejected';
            $conveyance->admin_remark = $approval->remarks;
            $conveyance->save();
        }
    }
}
