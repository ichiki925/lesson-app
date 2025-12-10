<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonSlot;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LessonSlotController extends Controller
{
    /**
     * 空き枠一覧取得（カレンダー表示用）
     * GET /api/lesson-slots?teacher_id=1&start_date=2025-12-10&end_date=2025-12-16
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $teacherId = $request->teacher_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // 空き枠を取得
        $slots = LessonSlot::where('teacher_id', $teacherId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // 日付でグループ化
        $groupedSlots = $slots->groupBy('date')->map(function ($dateSlots) {
            return $dateSlots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration' => $slot->duration,
                    'is_available' => $slot->is_available,
                    'has_reservation' => $slot->reservations()->exists(),
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $groupedSlots
        ]);
    }

    /**
     * 空き枠作成（単発）
     * POST /api/lesson-slots
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|in:30,60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 終了時刻を計算
            $startTime = Carbon::createFromFormat('H:i', $request->start_time);
            $endTime = $startTime->copy()->addMinutes($request->duration);

            // 重複チェック（同じ先生、同じ日、時間が重なる枠）
            $overlapping = LessonSlot::where('teacher_id', $request->teacher_id)
                ->whereDate('date', $request->date)
                ->where(function ($q) use ($request, $endTime) {
                    // 既存の枠の開始 < 新しい枠の終了
                    // かつ 既存の枠の終了 > 新しい枠の開始
                    $q->whereTime('start_time', '<', $endTime->format('H:i:s'))
                    ->whereTime('end_time', '>', $request->start_time);
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'この時間帯は既に登録されています'
                ], 422);
            }

            // 空き枠作成
            $slot = LessonSlot::create([
                'teacher_id' => $request->teacher_id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $endTime->format('H:i:s'),
                'duration' => $request->duration,
                'is_available' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '空き枠を作成しました',
                'data' => $slot
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '空き枠の作成に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 空き枠更新
     * PUT /api/lesson-slots/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date|after_or_equal:today',
            'start_time' => 'sometimes|date_format:H:i',
            'duration' => 'sometimes|in:30,60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 空き枠を取得
            $slot = LessonSlot::find($id);

            if (!$slot) {
                return response()->json([
                    'success' => false,
                    'message' => '空き枠が見つかりません'
                ], 404);
            }

            // 予約が入っているかチェック
            if ($slot->reservations()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'この空き枠には予約が入っているため変更できません'
                ], 422);
            }

            // 更新するデータを準備
            $updateData = [];

            if ($request->has('date')) {
                $updateData['date'] = $request->date;
            }

            if ($request->has('start_time') || $request->has('duration')) {
                // 新しい開始時刻と期間を取得（変更がなければ現在の値を使用）
                $startTime = $request->start_time ?? $slot->start_time->format('H:i:s');
                $duration = $request->duration ?? $slot->duration;

                // start_timeの形式を統一（H:i:s）
                if (strlen($startTime) === 5) {
                    // "16:00" の形式なら秒を追加
                    $startTime .= ':00';
                }

                // 終了時刻を計算
                $start = Carbon::createFromFormat('H:i:s', $startTime);
                $end = $start->copy()->addMinutes($duration);

                $updateData['start_time'] = $start->format('H:i:s');
                $updateData['end_time'] = $end->format('H:i:s');
                $updateData['duration'] = $duration;

                // 重複チェック（自分自身は除外）
                $date = $request->date ?? $slot->date;

                $overlapping = LessonSlot::where('teacher_id', $slot->teacher_id)
                    ->where('id', '!=', $id)  // 自分自身は除外
                    ->whereDate('date', $date)
                    ->where(function ($q) use ($updateData) {
                        $q->whereTime('start_time', '<', $updateData['end_time'])
                        ->whereTime('end_time', '>', $updateData['start_time']);
                    })
                    ->exists();

                if ($overlapping) {
                    return response()->json([
                        'success' => false,
                        'message' => 'この時間帯は既に登録されています'
                    ], 422);
                }
            }

            // 更新実行
            $slot->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '空き枠を更新しました',
                'data' => $slot->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '空き枠の更新に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 空き枠削除
     * DELETE /api/lesson-slots/{id}
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // 空き枠を取得
            $slot = LessonSlot::find($id);

            if (!$slot) {
                return response()->json([
                    'success' => false,
                    'message' => '空き枠が見つかりません'
                ], 404);
            }

            // 予約が入っているかチェック
            if ($slot->reservations()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'この空き枠には予約が入っているため削除できません'
                ], 422);
            }

            // 削除実行
            $slot->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '空き枠を削除しました'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '空き枠の削除に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
