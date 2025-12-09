<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'date',
        'start_time',
        'end_time',
        'duration',
        'is_available',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_available' => 'boolean',
    ];

    // リレーション: レッスン枠は1人の先生に属する
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    // リレーション: 1つのレッスン枠は複数の予約を持つ（通常は1つだが履歴のため）
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    // スコープ: 予約可能な枠のみ取得
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    // スコープ: 日付範囲で絞り込み
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}