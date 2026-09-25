<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 出勤ステータスが出勤中に更新できる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
        ]);
        $record = AttendanceRecord::where('user_id', $user->id)->first();

        $this->assertEquals('出勤中', $user->fresh()->attendance_status);
        $this->assertEquals(Carbon::today()->toDateString(), $record->date->toDateString());
    }

    /** @test */
    public function 出勤は１日１回しかできない(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $firstClockIn = $user->attendanceRecords()->first()->clock_in;

        sleep(1);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $this->assertEquals(1, $user->attendanceRecords()->count());
        $this->assertEquals($firstClockIn, $user->attendanceRecords()->first()->clock_in);
    }

    /** @test */
    public function 休憩中のステータスが正しく表示される(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $this->assertEquals('休憩中', $user->fresh()->attendance_status);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);
        $this->assertEquals('出勤中', $user->fresh()->attendance_status);
    }

    /** @test */
    public function 出勤ステータスが退勤済に更新できる(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $this->assertEquals('退勤済', $user->fresh()->attendance_status);
    }

    /** @test */
    public function 修正申請の備考欄が空白の場合はエラーになる(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->post("/attendance/{$record->id}", [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '',
        ]);

        $response->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    /** @test */

    public function 出勤打刻よりも前の時刻での退勤打刻は拒否される(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->post("/attendance/{$record->id}", [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'comment' => '修正します',
        ]);

        $response->assertSessionHasErrors('new_clock_in');
    }

    /** @test */
    public function 一般ユーザー修正申請が「承認待ち」になる(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user)->post("/attendance/{$record->id}", [
            'new_clock_in' => '09:00',
            'new_clock_out' => '19:00',
            'comment' => '残業のため修正します',
        ]);

        $this->assertDatabaseHas('applications', [
            'attendance_record_id' => $record->id,
            'approval_status' => '承認待ち',
        ]);
    }
}
