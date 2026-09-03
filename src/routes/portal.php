<?php

use Illuminate\Support\Facades\Route;
use ME\Hr\Http\Controllers\Portal\PortalAttendanceController;
use ME\Hr\Http\Controllers\Portal\PortalAuthController;
use ME\Hr\Http\Controllers\Portal\PortalConveyanceController;
use ME\Hr\Http\Controllers\Portal\PortalDashboardController;
use ME\Hr\Http\Controllers\Portal\PortalLeaveController;
use ME\Hr\Http\Controllers\Portal\PortalNoticeController;
use ME\Hr\Http\Controllers\Portal\PortalProfileController;

Route::middleware(['web'])
    ->prefix('employee-portal')
    ->name('employee-portal.')
    ->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login'])->name('login.submit');
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');
    });

Route::middleware(['web', 'auth:employee'])
    ->prefix('employee-portal')
    ->name('employee-portal.')
    ->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');

        Route::post('/attendance/punch', [PortalAttendanceController::class, 'punch'])->name('attendance.punch');
        Route::get('/attendance', [PortalAttendanceController::class, 'index'])->name('attendance.index');

        Route::get('/profile', [PortalProfileController::class, 'show'])->name('profile');
        Route::post('/profile/password', [PortalProfileController::class, 'updatePassword'])->name('profile.password');

        Route::get('/leave', [PortalLeaveController::class, 'index'])->name('leave.index');
        Route::post('/leave', [PortalLeaveController::class, 'store'])->name('leave.store');

        Route::get('/conveyance', [PortalConveyanceController::class, 'index'])->name('conveyance.index');
        Route::post('/conveyance', [PortalConveyanceController::class, 'store'])->name('conveyance.store');

        Route::get('/notices', [PortalNoticeController::class, 'index'])->name('notices.index');
    });
