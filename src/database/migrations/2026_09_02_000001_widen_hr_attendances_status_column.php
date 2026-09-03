<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'status' (20 chars) is too narrow for combined statuses that
        // AttendanceController::calculateStatus() can produce, e.g.
        // "Late and Punch Missing" (23 chars) — widen it to fit safely.
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->change();
        });
    }
};
