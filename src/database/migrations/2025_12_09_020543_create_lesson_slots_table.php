<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration')->comment('30 or 60 minutes');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            // 同じ先生が同じ日時に重複した枠を作らないようにインデックス
            $table->index(['teacher_id', 'date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_slots');
    }
};