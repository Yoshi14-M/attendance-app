<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $this->user = User::factory()->create();
    }

    /** @test */
    public function 自分が行った勤怠情報が全て表示されている(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-10-01', 'clock_in' => '09:00:00', 'clock_out' => '18:00:00']);
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-10-02', 'clock_in' => '09:15:00', 'clock_out' => '18:30:00']);
        AttendanceRecord::factory()->for(User::factory())->create(['date' => '2026-10-01', 'clock_in' => '07:45:00', 'clock_out' => '16:45:00']);

        $response = $this->actingAs($this->user)->get('/attendance/list');

        $response->assertSeeInOrder(['10/01(木)', '09:00', '18:00', '10/02(金)', '09:15', '18:30']);
        $response->assertDontSee('07:45');
    }

    /** @test */
    public function 勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('<p class="current-month">2026/10</p>', false);
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-09-10', 'clock_in' => '08:45:00', 'clock_out' => '17:45:00']);

        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSee('href="?date=2026-09"', false);

        $this->actingAs($this->user)->get('/attendance/list?date=2026-09')
            ->assertSee('<p class="current-month">2026/09</p>', false)
            ->assertSeeInOrder(['09/10(木)', '08:45', '17:45']);
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-11-10', 'clock_in' => '08:45:00', 'clock_out' => '17:45:00']);

        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertSee('href="?date=2026-11"', false);

        $this->actingAs($this->user)->get('/attendance/list?date=2026-11')
            ->assertSee('<p class="current-month">2026/11</p>', false)
            ->assertSeeInOrder(['11/10(火)', '08:45', '17:45']);
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $record = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-10-01']);

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSee('href="'.url('/attendance/'.$record->id).'"', false);

        $this->actingAs($this->user)->get('/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('10月1日');
    }
}
