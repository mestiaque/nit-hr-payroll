<?php

namespace ME\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HrConveyanceRequest extends BaseHrModel
{
    protected $table = 'hr_conveyance_requests';

    protected $casts = [
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function attachmentFile(): HasOne
    {
        $fileClass = class_exists(\App\Models\File::class) ? \App\Models\File::class : self::class;
        return $this->hasOne($fileClass, 'fileable_id')->where('fileable_type', self::class)->where('use_case', 'attachment');
    }
}
