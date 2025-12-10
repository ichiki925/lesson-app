<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\LessonSlotController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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

// 先生側: 空き枠管理API（認証は後で追加）
Route::prefix('lesson-slots')->group(function () {
    Route::get('/', [LessonSlotController::class, 'index']);
    Route::post('/', [LessonSlotController::class, 'store']);
    Route::put('/{id}', [LessonSlotController::class, 'update']);
    Route::delete('/{id}', [LessonSlotController::class, 'destroy']);
});