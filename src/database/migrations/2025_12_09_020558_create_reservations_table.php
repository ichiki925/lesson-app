<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_slot_id')->constrained()->onDelete('cascade');
            $table->string('student_name');
            $table->string('student_email');
            $table->string('student_phone')->nullable();
            $table->integer('course_type')->comment('30 or 60 minutes');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending')->comment('pending, confirmed, cancelled');
            $table->string('cancel_token')->unique();
            $table->timestamps();

            // キャンセルトークンで検索しやすくする
            $table->index('cancel_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};