<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LessonSlot;
use Carbon\Carbon;

class LessonSlotSeeder extends Seeder
{
    public function run(): void
    {
        $teacher_id = 1; // Ichiki先生
        $startDate = Carbon::today();

        // 今日から2週間分のレッスン枠を作成
        for ($day = 0; $day < 14; $day++) {
            $date = $startDate->copy()->addDays($day);

            // 平日のみ（月〜金）
            if ($date->isWeekday()) {
                // 午前の枠（10:00-12:00）
                $this->createSlots($teacher_id, $date, '10:00', '12:00');

                // 午後の枠（14:00-17:00）
                $this->createSlots($teacher_id, $date, '14:00', '17:00');
            }

            // 土曜日
            if ($date->isSaturday()) {
                // 午前の枠（10:00-12:00）
                $this->createSlots($teacher_id, $date, '10:00', '12:00');
            }
        }
    }

    private function createSlots($teacher_id, $date, $startTime, $endTime)
    {
        $current = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);

        while ($current->lt($end)) {
            // 30分枠
            if ($current->copy()->addMinutes(30)->lte($end)) {
                LessonSlot::create([
                    'teacher_id' => $teacher_id,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $current->format('H:i:s'),
                    'end_time' => $current->copy()->addMinutes(30)->format('H:i:s'),
                    'duration' => 30,
                    'is_available' => true,
                ]);
            }

            // 60分枠
            if ($current->copy()->addMinutes(60)->lte($end)) {
                LessonSlot::create([
                    'teacher_id' => $teacher_id,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $current->format('H:i:s'),
                    'end_time' => $current->copy()->addMinutes(60)->format('H:i:s'),
                    'duration' => 60,
                    'is_available' => true,
                ]);
            }

            $current->addMinutes(30);
        }
    }
}