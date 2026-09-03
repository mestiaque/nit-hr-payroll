<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_notices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('published_at');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->index(['status', 'published_at'], 'idx_hr_notice_status_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_notices');
    }
};
