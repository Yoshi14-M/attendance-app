<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceBreak extends Model
{
    use HasFactory;

    /**
     * 一括代入の許可項目（ホワイトリスト）
     */
    protected $fillable = [
        'attendance_id',
        'break_in',
        'break_out',
    ];

    /**
     * 複数の休憩レコードが１つの勤怠に属する（多対１）
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
