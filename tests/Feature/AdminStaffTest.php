<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $this->admin = User::factory()->create(['admin_status' => true, 'email' => 'admin@example.com']);
        $this->staff = User::factory()->create(['name' => '山田 太郎', 'email' => 'taro@example.com']);
    }

    /** @test */
    public function 管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        User::factory()->create(['name' => '佐藤 花子', 'email' => 'hanako@example.com']);

        $this->actingAs($this->admin)->get('/admin/staff/list')
            ->assertOk()
            ->assertSeeInOrder(['山田 太郎', 'taro@example.com'])
            ->assertSeeInOrder(['佐藤 花子', 'hanako@example.com'])
            ->assertDontSee('admin@example.com');
    }

    /** @test */
    public function ユーザーの勤怠情報が正しく表示される(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-10-01', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        AttendanceRecord::factory()->for(User::factory())->create(['date' => '2026-10-01', 'clock_in' => '07:45:00', 'clock_out' => '16:45:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertOk()
            ->assertSee('山田 太郎さんの勤怠')
            ->assertSee('<p class="current-month">2026/10</p>', false)
            ->assertSeeInOrder(['10/01(木)', '09:00', '18:00', '9:00'])
            ->assertDontSee('07:45');
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-09-10', 'clock_in' => '08:45:00', 'clock_out' => '17:45:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="?date=2026-09"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-09')
            ->assertSee('<p class="current-month">2026/09</p>', false)
            ->assertSeeInOrder(['09/10(木)', '08:45', '17:45']);
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-11-10', 'clock_in' => '08:45:00', 'clock_out' => '17:45:00']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="?date=2026-11"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id.'?date=2026-11')
            ->assertSee('<p class="current-month">2026/11</p>', false)
            ->assertSeeInOrder(['11/10(火)', '08:45', '17:45']);
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $record = AttendanceRecord::factory()->for($this->staff)->create(['date' => '2026-10-01']);

        $this->actingAs($this->admin)->get('/admin/attendance/staff/'.$this->staff->id)
            ->assertSee('href="'.url('/attendance/'.$record->id).'"', false);

        $this->actingAs($this->admin)->get('/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('value="山田 太郎"', false)
            ->assertSee('value="10月1日"', false);
    }
}
