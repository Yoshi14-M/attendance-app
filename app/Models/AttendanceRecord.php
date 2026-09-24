<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    /**
     * 一括代入の許可項目（ホワイトリスト）
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * 型変換
     */
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * 複数の勤怠レコードが１つのユーザーに属する（多対１）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この勤怠レコードの休憩打刻一覧（１対多）
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * この勤怠レコードの修正申請一覧（１対多）
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 承認待ちの修正申請（存在する場合のみ）
     */
    public function pendingApplication(): ?Application
    {
        return $this->applications->firstWhere('approval_status', '承認待ち');
    }

    /**
     * 休憩時間の合計（秒）
     */
    public function getTotalBreakSecondsAttribute(): int
    {
        return $this->breaks
            ->filter(fn(AttendanceBreak $break) => $break->break_in && $break->break_out)
            ->sum(function (AttendanceBreak $break) {
                return Carbon::parse($break->break_out)->diffInSeconds(Carbon::parse($break->break_in));
            });
    }

    /**
     * 休憩合計時間（H:i:s形式）
     */
    public function getTotalBreakTimeAttribute(): ?string
    {
        // 休憩が無い場合はnull
        if ($this->breaks->isEmpty()) {
            return null;
        }

        return gmdate('H:i:s', $this->total_break_seconds);
    }

    /**
     * 実労働時間（H:i:s形式）
     */
    public function getTotalTimeAttribute(): ?string
    {
        // 出勤していない場合（退勤していない場合含む）はnull
        if (!$this->clock_in || !$this->clock_out) {
            return null;
        }

        $workedSeconds = Carbon::parse($this->clock_out)->diffInSeconds(Carbon::parse($this->clock_in));
        $seconds = max(0, $workedSeconds - $this->total_break_seconds);

        return gmdate('H:i:s', $seconds);
    }
}
