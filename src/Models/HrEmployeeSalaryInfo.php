<?php

namespace ME\Hr\Models;

require_once __DIR__.'/../Support/optional_audit_package.php';

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ME\Audit\Contracts\HasAuditParent;
use ME\Audit\Traits\HasAudit;

class HrEmployeeSalaryInfo extends BaseHrModel implements HasAuditParent
{
    use HasAudit;

    protected $table = 'hr_employee_salary_infos';

    protected $auditRelations = [
        'payment_method_id' => ['relation' => 'paymentMethod', 'display' => 'name'],
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(HrPaymentMethod::class, 'payment_method_id');
    }

    public function auditParent(): ?Model
    {
        return $this->employee;
    }

    public function getSalaryInfoStatusAttribute(): string
    {
        return $this->status ? 'active' : 'inactive';
    }
}
