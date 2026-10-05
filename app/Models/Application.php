<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    /**
     * 一括代入の許可項目（ホワイトリスト）
     */
    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'new_date',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'approval_status',
        'application_date',
    ];

    /**
     * 型変換
     */
    protected $casts = [
        'new_date' => 'date',
        'application_date' => 'datetime',
    ];

    /**
     * 複数の修正申請が１つのユーザーに属する（多対１）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 複数の修正申請が１つの勤怠レコードに属する（多対１）
     * （blade側で $application->AttendanceRecord として参照されるため、あえてアッパーキャメルのメソッド名にしている。）
     */
    public function AttendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    /**
     * この申請に紐づく修正案一覧（１対多）
     */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ProposalBreak::class);
    }

    /**
     * 申請出勤時間を取得
     */
    public function getNewClockInAttribute(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    /**
     * 申請退勤時間を取得
     */
    public function getNewClockOutAttribute(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }
}
