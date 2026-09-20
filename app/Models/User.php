<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * このユーザーの勤怠打刻一覧（１対多）
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * このユーザーの修正申請一覧（１対多）
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * 現在の勤怠ステータスを取得
     * （勤務外 / 出勤中 / 休憩中 / 退勤済）
     */
    public function getAttendanceStatusAttribute(): string
    {
        // 現在の勤怠レコードを取得
        $today = $this->attendanceRecords()
            ->whereDate('date', now()->toDateString())
            ->first();

        // 勤怠レコードがない場合（出勤打刻が空白の場合含む）
        if (!$today || !$today->clock_in) {
            return '勤務外';
        }

        // 退勤打刻がある場合
        if ($today->clock_out) {
            return '退勤済';
        }

        // 休憩終了時間を取得（休憩終了時間がない場合は休憩中）
        $onBreak = $today->breaks()->whereNull('break_out')->exists();

        return $onBreak ? '休憩中' : '出勤中';
    }
}
