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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $teacherId = $request->user()->id;
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
            $overlapping = LessonSlot::where('teacher_id', $request->user()->id)
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
                'teacher_id' => $request->user()->id,
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

            // 変更後（ログインユーザーの空き枠のみ取得）
            $slot = LessonSlot::where('id', $id)
                ->where('teacher_id', $request->user()->id)
                ->first();

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
    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $slot = LessonSlot::where('id', $id)
                ->where('teacher_id', $request->user()->id)
                ->first();

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

    /**
     * 空き枠一括作成
     * POST /api/lesson-slots/bulk
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.duration' => 'required|in:30,60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $createdSlots = [];
            $errors = [];

            foreach ($request->slots as $index => $slotData) {
                // 終了時刻を計算
                $startTime = Carbon::createFromFormat('H:i', $slotData['start_time']);
                $endTime = $startTime->copy()->addMinutes($slotData['duration']);

                // ① 配列内での重複チェック（flagを使う）
                $hasInternalOverlap = false;

                foreach ($createdSlots as $created) {
                    $createdStart = Carbon::parse($created->start_time);
                    $createdEnd = Carbon::parse($created->end_time);

                    // 時間帯の重なり判定（境界ちょうどはOKにする）
                    if ($startTime->lt($createdEnd) && $endTime->gt($createdStart)) {
                        $errors[] = [
                            'index' => $index,
                            'start_time' => $slotData['start_time'],
                            'message' => 'この時間帯は既に登録されています（同一リクエスト内）'
                        ];
                        $hasInternalOverlap = true;
                        break; // 内側のforeachだけ抜ける
                    }
                }

                if ($hasInternalOverlap) {
                    // このスロットは登録せず、次のスロットへ
                    continue;
                }

                // ② DB 上の既存枠との重複チェック（既に作成したスロットは除外）
                $query = LessonSlot::where('teacher_id', $request->user()->id)
                    ->whereDate('date', $request->date);

                // 既に作成したスロットがあれば除外
                if (!empty($createdSlots)) {
                    $createdIds = collect($createdSlots)->pluck('id')->filter()->toArray();
                    if (!empty($createdIds)) {
                        $query->whereNotIn('id', $createdIds);
                    }
                }

                $startTimeStr = $slotData['start_time'] . ':00';  // ← 秒を追加
                $endTimeStr = $endTime->format('H:i:s');

                $overlappingSlots = $query->where(function ($q) use ($startTimeStr, $endTimeStr) {
                        $q->whereTime('start_time', '<', $endTimeStr)
                        ->whereTime('end_time', '>', $startTimeStr);
                    })
                    ->get(['id', 'start_time', 'end_time']);

                \Log::info('DB重複チェック', [
                    '新規スロット' => $startTimeStr . '-' . $endTimeStr,
                    '既存枠数' => $overlappingSlots->count(),
                    '既存枠' => $overlappingSlots->toArray()
                ]);

                $overlapping = $overlappingSlots->count() > 0;

                if ($overlapping) {
                    $errors[] = [
                        'index' => $index,
                        'start_time' => $slotData['start_time'],
                        'message' => 'この時間帯は既に登録されています（DB上）'
                    ];
                    continue;
                }
                // 空き枠作成
                $slot = LessonSlot::create([
                    'teacher_id' => $request->user()->id,
                    'date' => $request->date,
                    'start_time' => $slotData['start_time'],
                    'end_time' => $endTime->format('H:i:s'),
                    'duration' => $slotData['duration'],
                    'is_available' => true,
                ]);

                $createdSlots[] = $slot;
            }

            // エラーがあれば全てロールバック
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => '一部の空き枠で重複エラーが発生しました',
                    'errors' => $errors
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdSlots) . '件の空き枠を作成しました',
                'data' => $createdSlots
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '空き枠の一括作成に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
