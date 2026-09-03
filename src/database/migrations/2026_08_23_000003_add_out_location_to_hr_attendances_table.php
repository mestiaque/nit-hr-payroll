<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->decimal('out_latitude', 10, 7)->nullable()->after('longitude');
            $table->decimal('out_longitude', 10, 7)->nullable()->after('out_latitude');
            $table->text('in_address')->nullable()->after('out_longitude');
            $table->text('out_address')->nullable()->after('in_address');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropColumn(['out_latitude', 'out_longitude', 'in_address', 'out_address']);
        });
    }
};
