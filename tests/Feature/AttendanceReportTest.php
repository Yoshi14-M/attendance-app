<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ゲストはレポートページにアクセスできない(): void
    {
        $this->get('/attendance/report')->assertRedirect('/login');
    }

    /** @test */
    public function 認証ユーザーの統計情報が正しく計算される(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));
        $user = User::factory()->create();

        collect([
            ['2026-09-01', '09:00:00', '18:00:00'], // 通常 実働8h
            ['2026-09-02', '09:30:00', '18:00:00'], // 遅刻 実働7.5h
            ['2026-09-03', '09:00:00', '17:00:00'], // 早退 実働7h
            ['2026-09-04', '08:00:00', '21:00:00'], // 長時間 実働12h（残業4h）
            ['2026-09-07', '09:00:00', '20:00:00'], // 実働10h（残業2h。ちょうど10hは長時間に含めない）
            ['2026-08-03', '09:00:00', '18:00:00'], // 前月 実働8h
        ])->each(function (array $day) use ($user): void {
            $record = AttendanceRecord::factory()->for($user)->create([
                'date' => $day[0],
                'clock_in' => $day[1],
                'clock_out' => $day[2],
            ]);
            $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);
        });

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total_work_minutes' => 3150,
            'total_overtime_minutes' => 360,
            'avg_work_minutes' => 525,
        ]);
        $response->assertViewHas('monthlyTrend', function (array $trend): bool {
            $byMonth = collect($trend)->keyBy('month');

            return count($trend) === 6
                && $byMonth['2026/09']['work_minutes'] === 2670
                && $byMonth['2026/09']['overtime_minutes'] === 360
                && $byMonth['2026/08']['work_minutes'] === 480
                && $byMonth['2026/07']['work_minutes'] === 0;
        });
        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    /** @test */
    public function 勤怠記録がないユーザーでも安全に処理される(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertOk();
        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);
        $response->assertViewHas('monthlyTrend', fn (array $trend): bool => count($trend) === 6
            && collect($trend)->every(fn (array $row): bool => $row['work_minutes'] === 0 && $row['overtime_minutes'] === 0));
        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }
}
