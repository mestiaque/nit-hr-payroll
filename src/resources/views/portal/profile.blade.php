@extends('hr::portal.layouts.app')

@section('title', 'My Profile - Employee Portal')
@section('page-title', 'My Profile')

@section('content')
<div class="content-grid">
    <div>
        <div class="card profile-header-card">
            <div class="profile-header">
                <div class="profile-avatar-wrap">
                    <img src="{{ asset($employee->image('md')) }}" alt="photo" class="profile-avatar">
                    <span class="profile-avatar-badge"><i class="fa-solid fa-check"></i></span>
                </div>
                <div style="font-weight:800; font-size:18px; margin-top:14px;">{{ $employee->name }}</div>
                <div style="color:var(--muted); font-size:13px; margin-top:2px;">
                    {{ optional($employee->designation)->name ?? '-' }} &middot; ID: {{ $employee->employee_id }}
                </div>
                <div class="profile-badge-row">
                    <span class="badge"><i class="fa-solid fa-building"></i>{{ optional($employee->department)->name ?? '-' }}</span>
                    <span class="badge"><i class="fa-solid fa-calendar-check"></i>Joined {{ optional($employee->joining_date)->format('M Y') ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="fa-solid fa-briefcase" style="color:var(--primary); margin-right:6px;"></i>Employment Details</div>
            <div class="detail-list">
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Designation</div>
                        <div class="detail-value">{{ optional($employee->designation)->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Department</div>
                        <div class="detail-value">{{ optional($employee->department)->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-layer-group"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Section</div>
                        <div class="detail-value">{{ optional($employee->section)->name ?? '-' }}</div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Joining Date</div>
                        <div class="detail-value">{{ optional($employee->joining_date)->format('d M Y') ?? '-' }}</div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-phone"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Mobile</div>
                        <div class="detail-value">{{ $employee->mobile ?? '-' }}</div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-icon"><i class="fa-solid fa-droplet"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Blood Group</div>
                        <div class="detail-value">{{ $employee->blood_group ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-lock" style="color:var(--primary); margin-right:6px;"></i>Change Password</div>
        <form method="POST" action="{{ route('employee-portal.profile.password') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <div class="password-field-wrap">
                    <input type="password" name="current_password" id="current-password" class="form-control" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current-password', this)"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="password-field-wrap">
                    <input type="password" name="new_password" id="new-password" class="form-control" required minlength="6">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new-password', this)"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="password-field-wrap">
                    <input type="password" name="new_password_confirmation" id="new-password-confirmation" class="form-control" required minlength="6">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new-password-confirmation', this)"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-key" style="margin-right:6px;"></i>Update Password</button>
        </form>
    </div>
</div>
@endsection
