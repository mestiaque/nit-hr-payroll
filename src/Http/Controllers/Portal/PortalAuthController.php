<?php

namespace ME\Hr\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use ME\Hr\Models\HrEmployee;

class PortalAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('employee')->check()) {
            return redirect()->route('employee-portal.dashboard');
        }

        return view('hr::portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'employee_id' => 'required|string',
            'password' => 'required|string',
        ]);

        $employee = HrEmployee::where('employee_id', $credentials['employee_id'])->first();
        $login = $employee?->portalLogin;

        if (!$employee || !$login || !$login->is_active || !Hash::check($credentials['password'], $login->password)) {
            return back()->withInput(['employee_id' => $credentials['employee_id']])
                ->with('error', 'Invalid Employee ID or password.');
        }

        Auth::guard('employee')->login($login, $request->boolean('remember'));
        $login->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();

        return redirect()->route('employee-portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('employee')->logout();

        return redirect()->route('employee-portal.login');
    }
}
