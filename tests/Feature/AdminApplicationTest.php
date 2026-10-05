<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['admin_status' => true]);
    }

    /**
     * 指定ユーザーの勤怠と、それに対する修正申請を作成する
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createApplicationFor(User $user, array $attributes = []): Application
    {
        $record = AttendanceRecord::factory()->for($user)->create([
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        return Application::factory()->create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
            ...$attributes,
        ]);
    }

    /** @test */
    public function 承認待ちの修正申請が全て表示されている(): void
    {
        $this->createApplicationFor(User::factory()->create(['name' => '山田 太郎']), ['comment' => '太郎の申請']);
        $this->createApplicationFor(User::factory()->create(['name' => '佐藤 花子']), ['comment' => '花子の申請']);

        $this->actingAs($this->admin)->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['<p class="table__description--item">承認待ち</p>', '山田 太郎'], false)
            ->assertSee('太郎の申請')
            ->assertSee('花子の申請');
    }

    /** @test */
    public function 承認済みの修正申請が全て表示されている(): void
    {
        $this->createApplicationFor(User::factory()->create(['name' => '山田 太郎']), ['comment' => '承認済みの申請', 'approval_status' => '承認済み']);

        $this->actingAs($this->admin)->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['<p class="table__description--item">承認済み</p>', '山田 太郎', '承認済みの申請'], false);
    }

    /** @test */
    public function 修正申請の詳細内容が正しく表示されている(): void
    {
        $application = $this->createApplicationFor(User::factory()->create(['name' => '山田 太郎']), [
            'new_clock_in' => '08:30:00',
            'new_clock_out' => '19:15:00',
            'comment' => '早出と残業のため',
        ]);
        $application->proposalBreaks()->create(['break_in' => '12:10:00', 'break_out' => '12:50:00']);

        $this->actingAs($this->admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="山田 太郎"', false)
            ->assertSee('value="10月1日"', false)
            ->assertSee('value="08:30"', false)
            ->assertSee('value="19:15"', false)
            ->assertSee('value="12:10"', false)
            ->assertSee('value="12:50"', false)
            ->assertSee('早出と残業のため');
    }

    /** @test */
    public function 修正申請の承認処理が正しく行われ勤怠情報が更新される(): void
    {
        $application = $this->createApplicationFor(User::factory()->create(), [
            'new_clock_in' => '08:30:00',
            'new_clock_out' => '19:15:00',
            'comment' => '早出と残業のため',
        ]);
        $application->proposalBreaks()->create(['break_in' => '12:10:00', 'break_out' => '12:50:00']);
        $application->proposalBreaks()->create(['break_in' => '15:00:00', 'break_out' => '15:10:00']);

        $this->actingAs($this->admin)->post('/stamp_correction_request/approve/'.$application->id)
            ->assertRedirect('/stamp_correction_request/list');

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'approval_status' => '承認済み']);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $application->attendance_record_id,
            'clock_in' => '08:30:00',
            'clock_out' => '19:15:00',
            'comment' => '早出と残業のため',
        ]);
        $this->assertDatabaseHas('attendance_breaks', ['attendance_record_id' => $application->attendance_record_id, 'break_in' => '12:10:00']);
        $this->assertDatabaseMissing('attendance_breaks', ['attendance_record_id' => $application->attendance_record_id, 'break_in' => '12:00:00']);
    }

    /** @test */
    public function 休憩開始が空の修正案を含む申請を承認しても500にならない(): void
    {
        $application = $this->createApplicationFor(User::factory()->create());
        $application->proposalBreaks()->create(['break_in' => null, 'break_out' => '13:00:00']);

        $this->actingAs($this->admin)->post('/stamp_correction_request/approve/'.$application->id)
            ->assertRedirect('/stamp_correction_request/list');

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'approval_status' => '承認済み']);
    }
}
