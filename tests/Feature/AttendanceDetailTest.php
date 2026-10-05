<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '山田 太郎']);
        $this->record = AttendanceRecord::factory()->for($this->user)->create([
            'date' => '2026-10-01',
            'clock_in' => '09:05:00',
            'clock_out' => '18:10:00',
        ]);
        $this->record->breaks()->create(['break_in' => '12:15:00', 'break_out' => '13:20:00']);
    }

    /** @test */
    public function 勤怠詳細画面の名前がログインユーザーの氏名になっている(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('value="山田 太郎"', false);
    }

    /** @test */
    public function 勤怠詳細画面の日付が選択した日付になっている(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月1日"', false);
    }

    /** @test */
    public function 出勤退勤にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('name="new_clock_in" value="09:05"', false)
            ->assertSee('name="new_clock_out" value="18:10"', false);
    }

    /** @test */
    public function 休憩にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        $this->actingAs($this->user)->get('/attendance/'.$this->record->id)
            ->assertSee('name="new_break_in[0]" value="12:15"', false)
            ->assertSee('name="new_break_out[0]" value="13:20"', false)
            // 休憩回数分のレコードに加えて、追加用の空欄が1つ表示される
            ->assertSee('name="new_break_in[1]" value=""', false);
    }

    /** @test */
    public function 他人の勤怠詳細は表示できない(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->get('/attendance/'.$this->record->id)
            ->assertNotFound();
    }
}
