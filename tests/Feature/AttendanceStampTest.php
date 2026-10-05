<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceStampTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));
        $this->user = User::factory()->create();
    }

    /**
     * 打刻画面のステータス表示部分のHTML
     */
    private function statusHtml(string $status): string
    {
        return '<p class="attendance__status--item">'.$status.'</p>';
    }

    /**
     * 指定した時刻に打刻する
     */
    private function stampAt(string $time, string $action): void
    {
        $this->travelTo(Carbon::parse("2026-10-15 {$time}"));
        $this->actingAs($this->user)->post('/attendance', ['action' => $action]);
    }

    /** @test */
    public function 現在の日時情報が画面と同じ形式で表示される(): void
    {
        $response = $this->actingAs($this->user)->get('/attendance');

        $response->assertSee('2026年10月15日(木)');
        $response->assertSee('value="09:00"', false);
    }

    /** @test */
    public function 勤務外の場合ステータスが勤務外と表示される(): void
    {
        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('勤務外'), false);
    }

    /** @test */
    public function 出勤中の場合ステータスが出勤中と表示される(): void
    {
        $this->stampAt('09:00:00', 'clock_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('出勤中'), false);
    }

    /** @test */
    public function 休憩中の場合ステータスが休憩中と表示される(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('12:00:00', 'break_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('休憩中'), false);
    }

    /** @test */
    public function 退勤済の場合ステータスが退勤済と表示される(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('18:00:00', 'clock_out');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('退勤済'), false);
    }

    /** @test */
    public function 出勤ボタンが正しく機能する(): void
    {
        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="clock_in">出勤</button>', false);

        $this->stampAt('09:00:00', 'clock_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('出勤中'), false);
    }

    /** @test */
    public function 退勤済のユーザーには出勤ボタンが表示されない(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('18:00:00', 'clock_out');

        $this->actingAs($this->user)->get('/attendance')
            ->assertDontSee('value="clock_in"', false);
    }

    /** @test */
    public function 出勤時刻が勤怠一覧画面で確認できる(): void
    {
        $this->stampAt('09:00:00', 'clock_in');

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSeeInOrder(['10/15(木)', '09:00']);
    }

    /** @test */
    public function 休憩入ボタンが正しく機能する(): void
    {
        $this->stampAt('09:00:00', 'clock_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="break_in">休憩入</button>', false);

        $this->stampAt('12:00:00', 'break_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('休憩中'), false);
    }

    /** @test */
    public function 休憩は一日に何回でもできる(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('12:00:00', 'break_in');
        $this->stampAt('12:30:00', 'break_out');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="break_in">休憩入</button>', false);
    }

    /** @test */
    public function 休憩戻ボタンが正しく機能する(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('12:00:00', 'break_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="break_out">休憩戻</button>', false);

        $this->stampAt('13:00:00', 'break_out');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('出勤中'), false);
    }

    /** @test */
    public function 休憩戻は一日に何回でもできる(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('12:00:00', 'break_in');
        $this->stampAt('12:30:00', 'break_out');
        $this->stampAt('15:00:00', 'break_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="break_out">休憩戻</button>', false);
        $this->assertSame(2, AttendanceRecord::first()->breaks()->count());
    }

    /** @test */
    public function 休憩時刻が勤怠一覧画面で確認できる(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('12:00:00', 'break_in');
        $this->stampAt('13:00:00', 'break_out');
        $this->stampAt('18:00:00', 'clock_out');

        // 一覧の休憩欄には休憩の合計時間が表示される
        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSeeInOrder(['10/15(木)', '09:00', '18:00', '1:00', '8:00']);
    }

    /** @test */
    public function 退勤ボタンが正しく機能する(): void
    {
        $this->stampAt('09:00:00', 'clock_in');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee('value="clock_out">退勤</button>', false);

        $this->stampAt('18:00:00', 'clock_out');

        $this->actingAs($this->user)->get('/attendance')
            ->assertSee($this->statusHtml('退勤済'), false)
            ->assertSee('お疲れ様でした。');
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる(): void
    {
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('18:00:00', 'clock_out');

        $this->actingAs($this->user)->get('/attendance/list')
            ->assertSeeInOrder(['10/15(木)', '09:00', '18:00']);
    }

    /** @test */
    public function 現在のステータスで押せない打刻は記録されない(): void
    {
        // 出勤前の休憩入・退勤
        $this->stampAt('08:00:00', 'break_in');
        $this->stampAt('08:30:00', 'clock_out');
        $this->assertDatabaseCount('attendance_records', 0);

        // 退勤後の休憩入
        $this->stampAt('09:00:00', 'clock_in');
        $this->stampAt('18:00:00', 'clock_out');
        $this->stampAt('18:30:00', 'break_in');
        $this->assertDatabaseCount('attendance_breaks', 0);
    }
}
