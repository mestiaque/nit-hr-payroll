<?php

namespace ME\Hr\Models;

class HrNotice extends BaseHrModel
{
    protected $table = 'hr_notices';

    protected $casts = [
        'published_at' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1)->where('published_at', '<=', now());
    }
}
