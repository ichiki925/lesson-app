<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_slot_id',
        'student_name',
        'student_email',
        'student_phone',
        'course_type',
        'notes',
        'status',
        'cancel_token',
    ];

    protected $casts = [
        'course_type' => 'integer',
    ];

    // リレーション: 予約は1つのレッスン枠に属する
    public function lessonSlot()
    {
        return $this->belongsTo(LessonSlot::class);
    }

    // モデル作成時に自動的にキャンセルトークンを生成
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (empty($reservation->cancel_token)) {
                $reservation->cancel_token = Str::random(32);
            }
        });
    }

    // スコープ: アクティブな予約のみ取得
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    // スコープ: キャンセル済みの予約のみ取得
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}