<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['admin_status' => true]);
        $this->record = AttendanceRecord::factory()
            ->for(User::factory()->state(['name' => '山田 太郎']))
            ->create([
                'date' => '2026-10-01',
                'clock_in' => '09:05:00',
                'clock_out' => '18:10:00',
                'comment' => '通常勤務',
            ]);
        $this->record->breaks()->create(['break_in' => '12:15:00', 'break_out' => '13:20:00']);
    }

    /**
     * 管理者として直接修正を送信する（指定した項目以外は正常な値）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->post('/attendance/'.$this->record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '管理者による修正',
            ...$overrides,
        ]);
    }

    /** @test */
    public function 勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        $this->actingAs($this->admin)->get('/attendance/'.$this->record->id)
            ->assertOk()
            ->assertSee('value="山田 太郎"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月1日"', false)
            ->assertSee('value="09:05"', false)
            ->assertSee('value="18:10"', false)
            ->assertSee('value="12:15"', false)
            ->assertSee('value="13:20"', false)
            ->assertSee('通常勤務');
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
        $this->submit(['new_break_out' => ['19:00']])
            ->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    /** @test */
    public function 備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        $this->submit(['comment' => ''])
            ->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    /** @test */
    public function 休憩終了時間だけが入力されている場合はエラーになり500にならない(): void
    {
        $this->submit(['new_break_in' => [''], 'new_break_out' => ['13:00']])
            ->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    /** @test */
    public function 修正内容が一般ユーザーの勤怠情報に反映される(): void
    {
        $this->submit(['new_clock_out' => '19:00', 'new_break_in' => ['12:00', '15:00'], 'new_break_out' => ['13:00', '15:15']])
            ->assertRedirect('/admin/attendance/list');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $this->record->id,
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
            'comment' => '管理者による修正',
        ]);
        $this->assertSame(2, $this->record->breaks()->count());
    }

    /** @test */
    public function 承認待ちの申請がある勤怠は管理者も修正できない(): void
    {
        Application::factory()->create([
            'user_id' => $this->record->user_id,
            'attendance_record_id' => $this->record->id,
        ]);

        $this->submit(['new_clock_in' => '10:00'])
            ->assertSessionHasErrors(['comment' => '承認待ちのため修正はできません。']);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $this->record->id,
            'clock_in' => '09:05:00',
        ]);
    }
}
