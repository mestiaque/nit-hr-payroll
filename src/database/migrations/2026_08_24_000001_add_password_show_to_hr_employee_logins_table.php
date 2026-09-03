<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_logins', function (Blueprint $table) {
            $table->string('password_show')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employee_logins', function (Blueprint $table) {
            $table->dropColumn('password_show');
        });
    }
};
