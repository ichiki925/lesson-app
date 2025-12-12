<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\LessonSlot;
use App\Mail\ReservationCreated;
use App\Mail\ReservationCanceled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReservationController extends Controller
{
    /**
     * 利用可能なレッスン枠一覧を取得
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableSlots(Request $request)
    {
        // 1. バリデーション
        $validated = $request->validate([
            'teacher_id'  => ['required', 'exists:teachers,id'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // 2. ベースクエリ
        $query = LessonSlot::query()
            ->where('teacher_id', $validated['teacher_id'])
            ->where('is_available', true);

        // 3. 期間で絞る（指定があれば）
        if (!empty($validated['start_date'])) {
            $query->whereDate('date', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('date', '<=', $validated['end_date']);
        }

        // 4. 予約済み（confirmed / pending）を除外
        $query->whereDoesntHave('reservations', function ($q) {
            $q->whereIn('status', ['pending', 'confirmed']);
        });

        // 5. 並び順
        $slots = $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // 6. レスポンス
        return response()->json([
            'data' => $slots,
        ]);
    }

    /**
     * 予約を作成
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 1. リクエストのバリデーション
        $validated = $request->validate([
            'lesson_slot_id' => ['required', 'exists:lesson_slots,id'],
            'student_name'   => ['required', 'string', 'max:255'],
            'student_email'  => ['required', 'email', 'max:255'],
            'student_phone'  => ['nullable', 'string', 'max:20'],
            'course_type'    => ['required', 'in:30,60'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        // 2. レッスン枠の存在確認と状態チェック
        $lessonSlot = LessonSlot::findOrFail($validated['lesson_slot_id']);

        // 3. 過去の日付チェック
        if ($lessonSlot->date < now()->toDateString()) {
            return response()->json([
                'message' => '過去の日付は予約できません。',
            ], 400);
        }

        if (!$lessonSlot->is_available) {
            return response()->json([
                'message' => 'このレッスン枠は既に予約されています。',
            ], 409);
        }

        // 4. 同じ枠への重複予約をチェック
        $existingReservation = Reservation::where('lesson_slot_id', $validated['lesson_slot_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existingReservation) {
            return response()->json([
                'message' => 'このレッスン枠は既に予約されています。',
            ], 409);
        }

        // 5. トランザクション開始
        try {
            DB::beginTransaction();

            // 予約を作成（cancel_tokenはモデルで自動生成される）
            $reservation = Reservation::create([
                'lesson_slot_id' => $validated['lesson_slot_id'],
                'student_name'   => $validated['student_name'],
                'student_email'  => $validated['student_email'],
                'student_phone'  => $validated['student_phone'] ?? null,
                'course_type'    => $validated['course_type'],
                'notes'          => $validated['notes'] ?? null,
                'status'         => 'confirmed',
            ]);

            // レッスン枠の is_available を false に更新
            $lessonSlot->update(['is_available' => false]);

            DB::commit();

            // 6. 確認メールを送信（生徒）
            Mail::to($reservation->student_email)
                ->send(new ReservationCreated($reservation));

            // 7. レスポンス
            return response()->json([
                'message' => '予約が完了しました。',
                'data'    => $reservation,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => '予約の処理中にエラーが発生しました。',
            ], 500);
        }
    }

    /**
     * 予約情報を取得
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // 1. 予約を取得
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => '予約が見つかりません。',
            ], 404);
        }

        // 2. リレーション情報も含める
        $reservation->load(['lessonSlot', 'lessonSlot.teacher']);

        // 3. レスポンス
        return response()->json([
            'data' => $reservation,
        ]);
    }

    /**
     * 予約をキャンセル（キャンセルトークン使用）
     * 
     * @param Request $request
     * @param string $cancelToken
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request, $cancelToken)
    {
        // 1. キャンセルトークンから予約を検索
        $reservation = Reservation::where('cancel_token', $cancelToken)->first();

        if (!$reservation) {
            return response()->json([
                'message' => '無効なキャンセルトークンです。',
            ], 404);
        }

        // 2. 既にキャンセルされていないか確認
        if ($reservation->status === 'cancelled') {
            return response()->json([
                'message' => 'この予約は既にキャンセルされています。',
            ], 409);
        }

        // 3. トランザクション開始
        try {
            DB::beginTransaction();

            // 予約ステータスを「cancelled」に更新
            $reservation->update([
                'status' => 'cancelled',
                'canceled_at' => now(),
            ]);

            // 関連するレッスン枠を再度利用可能に
            $lessonSlot = $reservation->lessonSlot;
            $lessonSlot->update(['is_available' => true]);

            DB::commit();

            // キャンセルメール送信
            Mail::to($reservation->student_email)->send(new ReservationCanceled($reservation));

            // 5. レスポンス
            return response()->json([
                'message' => '予約がキャンセルされました。',
                'data'    => $reservation,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'キャンセルの処理中にエラーが発生しました。',
            ], 500);
        }
    }

    /**
     * 生徒の予約履歴を取得（メールアドレスで検索）
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStudentReservations(Request $request)
    {
        // 1. リクエストのバリデーション
        $validated = $request->validate([
            'student_email' => ['required', 'email', 'max:255'],
        ]);

        // 2. メールアドレスに紐付いた予約一覧を取得
        $reservations = Reservation::where('student_email', $validated['student_email'])
            ->with(['lessonSlot', 'lessonSlot.teacher'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. レスポンス
        return response()->json([
            'data' => $reservations,
        ]);
    }
}
