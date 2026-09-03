<?php

namespace ME\Hr\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PortalProfileController extends PortalController
{
    public function show()
    {
        $employee = $this->employee();

        return view('hr::portal.profile', ['employee' => $employee]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $login = Auth::guard('employee')->user();

        if (!Hash::check($validated['current_password'], $login->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        $login->forceFill([
            'password' => $validated['new_password'],
            'must_change_password' => false,
        ])->save();

        return back()->with('success', 'Password changed successfully.');
    }
}
