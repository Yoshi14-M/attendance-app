<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $this->admin = User::factory()->create(['admin_status' => true]);
    }

    /**
     * 指定日の勤怠（12:00-13:00 の休憩付き）を作成する
     */
    private function createRecord(User $user, string $date, string $clockIn, string $clockOut): AttendanceRecord
    {
        $record = AttendanceRecord::factory()->for($user)->create([
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        return $record;
    }

    /** @test */
    public function その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        $taro = User::factory()->create(['name' => '山田 太郎']);
        $hanako = User::factory()->create(['name' => '佐藤 花子']);
        $this->createRecord($taro, '2026-10-15', '09:00:00', '18:00:00');
        $this->createRecord($hanako, '2026-10-15', '08:30:00', '19:30:00');

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSeeInOrder(['山田 太郎', '09:00', '18:00', '1:00', '8:00'])
            ->assertSeeInOrder(['佐藤 花子', '08:30', '19:30', '1:00', '10:00']);
    }

    /** @test */
    public function 遷移した際に現在の日付が表示される(): void
    {
        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('2026年10月15日の勤怠')
            ->assertSee('<p class="current-day">2026/10/15</p>', false);
    }

    /** @test */
    public function 前日を押下した時に前の日の勤怠情報が表示される(): void
    {
        $user = User::factory()->create(['name' => '山田 太郎']);
        $this->createRecord($user, '2026-10-14', '07:10:00', '16:10:00');

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('href="?date=2026-10-14"', false)
            ->assertDontSee('07:10');

        $this->actingAs($this->admin)->get('/admin/attendance/list?date=2026-10-14')
            ->assertSee('<p class="current-day">2026/10/14</p>', false)
            ->assertSeeInOrder(['山田 太郎', '07:10', '16:10']);
    }

    /** @test */
    public function 翌日を押下した時に次の日の勤怠情報が表示される(): void
    {
        $user = User::factory()->create(['name' => '山田 太郎']);
        $this->createRecord($user, '2026-10-16', '07:10:00', '16:10:00');

        $this->actingAs($this->admin)->get('/admin/attendance/list')
            ->assertSee('href="?date=2026-10-16"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/list?date=2026-10-16')
            ->assertSee('<p class="current-day">2026/10/16</p>', false)
            ->assertSeeInOrder(['山田 太郎', '07:10', '16:10']);
    }
}
