<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * 今日の予約一覧を取得
     * GET /api/dashboard/today-reservations
     */
    public function getTodayReservations(Request $request)
    {
        $teacherId = $request->user()->id;
        $today = Carbon::today();

        // 今日の予約を取得
        $reservations = Reservation::where('teacher_id', $teacherId)
            ->whereDate('lesson_date', $today)
            ->with(['student', 'lessonSlot'])
            ->orderBy('lesson_start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $today->format('Y-m-d'),
                'count' => $reservations->count(),
                'reservations' => $reservations,
            ],
        ]);
    }

    /**
     * 今週の予約一覧を取得
     * GET /api/dashboard/week-reservations
     */
    public function getWeekReservations(Request $request)
    {
        $teacherId = $request->user()->id;

        // 今週の月曜日と日曜日を取得
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // 今週の予約を取得
        $reservations = Reservation::where('teacher_id', $teacherId)
            ->whereBetween('lesson_date', [$startOfWeek, $endOfWeek])
            ->with(['student', 'lessonSlot'])
            ->orderBy('lesson_date')
            ->orderBy('lesson_start_time')
            ->get();

        // 日付でグループ化
        $groupedReservations = $reservations->groupBy(function ($reservation) {
            return Carbon::parse($reservation->lesson_date)->format('Y-m-d');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startOfWeek->format('Y-m-d'),
                'end_date' => $endOfWeek->format('Y-m-d'),
                'total_count' => $reservations->count(),
                'reservations_by_date' => $groupedReservations,
            ],
        ]);
    }

    /**
     * 統計情報を取得
     * GET /api/dashboard/stats
     */
    public function getStats(Request $request)
    {
        $teacherId = $request->user()->id;

        // 総予約数（キャンセル含む）
        $totalReservations = Reservation::where('teacher_id', $teacherId)->count();

        // 今月の予約数（キャンセル含まない）
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $thisMonthReservations = Reservation::where('teacher_id', $teacherId)
            ->whereBetween('lesson_date', [$startOfMonth, $endOfMonth])
            ->whereNull('canceled_at')
            ->count();

        // キャンセル率の計算
        $canceledReservations = Reservation::where('teacher_id', $teacherId)
            ->whereNotNull('canceled_at')
            ->count();

        $cancelRate = $totalReservations > 0
            ? round(($canceledReservations / $totalReservations) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_reservations' => $totalReservations,
                'this_month_reservations' => $thisMonthReservations,
                'canceled_reservations' => $canceledReservations,
                'cancel_rate' => $cancelRate,  // パーセンテージ
            ],
        ]);
    }

    /**
     * 直近の予約を取得
     * GET /api/dashboard/next-reservation
     */
    public function getNextReservation(Request $request)
    {
        $teacherId = $request->user()->id;
        $now = Carbon::now();

        // 現在時刻以降の最も近い予約を取得
        $nextReservation = Reservation::where('teacher_id', $teacherId)
            ->whereNull('canceled_at')
            ->where(function ($query) use ($now) {
                // 今日以降の予約
                $query->where('lesson_date', '>', $now->format('Y-m-d'))
                    // または今日で、まだ開始していない予約
                    ->orWhere(function ($q) use ($now) {
                        $q->where('lesson_date', $now->format('Y-m-d'))
                        ->where('lesson_start_time', '>', $now->format('H:i:s'));
                    });
            })
            ->with(['student', 'lessonSlot'])
            ->orderBy('lesson_date')
            ->orderBy('lesson_start_time')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'next_reservation' => $nextReservation,
            ],
        ]);
    }

    /**
     * 月間カレンダー表示用データを取得
     * GET /api/dashboard/month-reservations?year=2025&month=12
     */
    public function getMonthReservations(Request $request)
    {
        $teacherId = $request->user()->id;

        // 年月を取得（指定がなければ今月）
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        // 指定月の予約を取得
        $reservations = Reservation::where('teacher_id', $teacherId)
            ->whereBetween('lesson_date', [$startOfMonth, $endOfMonth])
            ->with(['student', 'lessonSlot'])
            ->orderBy('lesson_date')
            ->orderBy('lesson_start_time')
            ->get();

        // 日付ごとの予約数を集計
        $reservationsByDate = $reservations->groupBy(function ($reservation) {
            return Carbon::parse($reservation->lesson_date)->format('Y-m-d');
        })->map(function ($dateReservations) {
            return [
                'count' => $dateReservations->count(),
                'canceled_count' => $dateReservations->whereNotNull('canceled_at')->count(),
                'reservations' => $dateReservations,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'month' => $month,
                'start_date' => $startOfMonth->format('Y-m-d'),
                'end_date' => $endOfMonth->format('Y-m-d'),
                'total_count' => $reservations->count(),
                'reservations_by_date' => $reservationsByDate,
            ],
        ]);
    }
}