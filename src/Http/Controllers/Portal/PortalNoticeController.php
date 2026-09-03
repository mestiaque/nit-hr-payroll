<?php

namespace ME\Hr\Http\Controllers\Portal;

use ME\Hr\Models\HrNotice;

class PortalNoticeController extends PortalController
{
    public function index()
    {
        $notices = HrNotice::active()->orderByDesc('published_at')->limit(30)->get();

        return view('hr::portal.notices.index', [
            'employee' => $this->employee(),
            'notices' => $notices,
        ]);
    }
}
