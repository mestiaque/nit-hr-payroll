@extends('hr::portal.layouts.app')

@section('title', 'Login - Employee Portal')

@section('content')
<div class="card" style="width:100%; max-width:380px;">
    <div class="card-title" style="justify-content:center; font-size:18px;">Employee Login</div>
    <form method="POST" action="{{ route('employee-portal.login.submit') }}">
        @csrf
        <div class="form-group">
            <label class="form-label">Employee ID</label>
            <input type="text" name="employee_id" class="form-control" value="{{ old('employee_id') }}" required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <div class="password-field-wrap">
                <input type="password" name="password" id="login-password" class="form-control" required>
                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('login-password', this)"><i class="fa-regular fa-eye"></i></button>
            </div>
        </div>
        <div class="form-group" style="display:flex; align-items:center; gap:6px;">
            <input type="checkbox" name="remember" id="remember" style="width:auto;">
            <label for="remember" style="margin:0; font-size:13px; color:var(--muted);">Remember me</label>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
</div>
@endsection
