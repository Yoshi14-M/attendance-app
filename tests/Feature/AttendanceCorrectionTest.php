<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->record = AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    /**
     * 修正申請を送信する（指定した項目以外は正常な値）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)->post('/attendance/'.$this->record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '電車遅延のため',
            ...$overrides,
        ]);
    }

    /** @test */
    public function 出勤時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $this->submit(['new_clock_in' => '19:00', 'new_break_in' => [''], 'new_break_out' => ['']])
            ->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    /** @test */
    public function 休憩開始時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['19:00'], 'new_break_out' => ['']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    /** @test */
    public function 休憩終了時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => ['12:00'], 'new_break_out' => ['19:00']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    /** @test */
    public function 休憩終了時間だけが入力されている場合エラーメッセージが表示される(): void
    {
        $this->submit(['new_break_in' => [''], 'new_break_out' => ['13:00']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
        $this->assertDatabaseCount('applications', 0);
    }

    /** @test */
    public function 備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $this->submit(['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    /** @test */
    public function 修正申請処理が実行され管理者の承認画面と申請一覧画面に表示される(): void
    {
        $this->submit(['new_clock_out' => '19:00', 'comment' => '残業申請テスト'])
            ->assertRedirect('/attendance/list');

        $application = Application::firstOrFail();
        $admin = User::factory()->create(['admin_status' => true]);

        $this->actingAs($admin)->get('/stamp_correction_request/list')
            ->assertSee('残業申請テスト');
        $this->actingAs($admin)->get('/stamp_correction_request/approve/'.$application->id)
            ->assertSee('value="19:00"', false)
            ->assertSee('残業申請テスト');
    }

    /** @test */
    public function 承認待ちにログインユーザーが行った申請が全て表示されている(): void
    {
        $secondRecord = AttendanceRecord::factory()->for($this->user)->create(['date' => '2026-10-02']);
        $this->submit(['comment' => '一件目の申請']);
        $this->actingAs($this->user)->post('/attendance/'.$secondRecord->id, ['comment' => '二件目の申請']);

        $otherRecord = AttendanceRecord::factory()->for(User::factory())->create();
        Application::factory()->create([
            'user_id' => $otherRecord->user_id,
            'attendance_record_id' => $otherRecord->id,
            'comment' => '他人の申請',
        ]);

        $this->actingAs($this->user)->get('/stamp_correction_request/list')
            ->assertSee('一件目の申請')
            ->assertSee('二件目の申請')
            ->assertDontSee('他人の申請');
    }

    /** @test */
    public function 承認済みに管理者が承認した修正申請が全て表示されている(): void
    {
        $this->submit(['comment' => '承認される申請']);
        $application = Application::firstOrFail();
        $admin = User::factory()->create(['admin_status' => true]);
        $this->actingAs($admin)->post('/stamp_correction_request/approve/'.$application->id);

        $this->actingAs($this->user)->get('/stamp_correction_request/list')
            ->assertSeeInOrder(['<p class="table__description--item">承認済み</p>', '承認される申請'], false);
    }

    /** @test */
    public function 各申請の詳細を押下すると勤怠詳細画面に遷移する(): void
    {
        $this->submit();
        $application = Application::firstOrFail();

        $this->actingAs($this->user)->get('/stamp_correction_request/list')
            ->assertSee('href="'.url('/application/'.$application->id).'"', false);

        $this->actingAs($this->user)->get('/application/'.$application->id)
            ->assertRedirect('/attendance/'.$this->record->id);
    }

    /** @test */
    public function 承認待ちの勤怠は修正できない(): void
    {
        $this->submit(['comment' => '一回目']);

        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('承認待ちのため修正できません');

        $this->submit(['comment' => '二回目'])
            ->assertSessionHasErrors(['comment' => '承認待ちのため修正はできません。']);
        $this->assertDatabaseCount('applications', 1);
    }
}
