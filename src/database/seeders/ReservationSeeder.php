<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reservation;
use App\Models\LessonSlot;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        // 最初のレッスン枠にサンプル予約を作成
        $lessonSlot = LessonSlot::first();

        if ($lessonSlot) {
            Reservation::create([
                'lesson_slot_id' => $lessonSlot->id,
                'student_name' => '山田太郎',
                'student_email' => 'student@example.com',
                'student_phone' => '090-1234-5678',
                'course_type' => 30,
                'notes' => 'テスト予約です',
                'status' => 'confirmed',
            ]);

            // 予約されたら枠を unavailable にする
            $lessonSlot->update(['is_available' => false]);
        }
    }
}
