<?php

namespace ME\Hr\Http\Controllers\Portal;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use ME\Hr\Models\HrEmployee;

abstract class PortalController extends Controller
{
    protected function employee(): HrEmployee
    {
        return Auth::guard('employee')->user()->employee;
    }
}
