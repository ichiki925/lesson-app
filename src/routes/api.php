<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\LessonSlotController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;

// 生徒側: 予約関連API
Route::prefix('reservations')->group(function () {
    // 利用可能なレッスン枠一覧を取得
    Route::get('available-slots', [ReservationController::class, 'getAvailableSlots']);

    // 予約を作成
    Route::post('/', [ReservationController::class, 'store']);

    // 予約詳細を取得
    Route::get('/{id}', [ReservationController::class, 'show']);

    // 予約をキャンセル（キャンセルトークン使用）
    Route::post('cancel/{cancelToken}', [ReservationController::class, 'cancel']);

    // 生徒の予約履歴を取得
    Route::get('student/history', [ReservationController::class, 'getStudentReservations']);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// 認証が必要なエンドポイント
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('dashboard')->group(function () {
        Route::get('/today-reservations', [DashboardController::class, 'getTodayReservations']);
        Route::get('/week-reservations', [DashboardController::class, 'getWeekReservations']);
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/next-reservation', [DashboardController::class, 'getNextReservation']);
        Route::get('/month-reservations', [DashboardController::class, 'getMonthReservations']);
    });

    Route::prefix('lesson-slots')->group(function () {
        Route::get('/', [LessonSlotController::class, 'index']);
        Route::post('/', [LessonSlotController::class, 'store']);
        Route::put('/{id}', [LessonSlotController::class, 'update']);
        Route::delete('/{id}', [LessonSlotController::class, 'destroy']);
        Route::post('/bulk', [LessonSlotController::class, 'bulkStore']);
    });
});



