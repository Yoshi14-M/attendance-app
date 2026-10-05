<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 開発プロセス「ダミーデータが作成可能か」の検証
 * user1 でマイ勤怠レポートを開いたときに、仕様書記載の予測値になることを確認する。
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 当月の平日が「昨日まで」では17日に満たない月初でも、予測値どおりになることを確認する
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
        $this->seed();
    }

    /** @test */
    public function 全ユーザーに勤怠と休憩のダミーデータが作成される(): void
    {
        User::all()->each(function (User $user): void {
            $this->assertTrue($user->attendanceRecords()->exists(), "{$user->email} の勤怠がありません");
            $this->assertTrue($user->attendanceRecords()->has('breaks')->exists(), "{$user->email} の休憩がありません");
        });
        $this->assertTrue(User::where('email', 'user3@example.com')->value('admin_status'));
    }

    /** @test */
    public function user1のマイ勤怠レポートが仕様書の予測値と一致する(): void
    {
        $user1 = User::where('email', 'user1@example.com')->firstOrFail();

        $response = $this->actingAs($user1)->get('/attendance/report');

        $response->assertViewHas('summary', [
            'total_work_minutes' => 744 * 60,
            'total_overtime_minutes' => 10 * 60,
            'avg_work_minutes' => 8 * 60 + 5,
        ]);
        $response->assertViewHas('anomalies', [
            'late_count' => 2,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    /** @test */
    public function user1は当日の勤怠が無く打刻を試せる(): void
    {
        $user1 = User::where('email', 'user1@example.com')->firstOrFail();

        $this->assertSame('勤務外', $user1->attendance_status);
    }
}
