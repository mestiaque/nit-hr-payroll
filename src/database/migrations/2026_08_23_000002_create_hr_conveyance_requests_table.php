<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_conveyance_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('request_no', 30)->unique();
            $table->unsignedInteger('employee_id');
            $table->string('from_location', 191);
            $table->string('to_location', 191);
            $table->string('travel_by', 50)->nullable();
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('admin_remark')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->index('status', 'idx_hr_conv_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_conveyance_requests');
    }
};
