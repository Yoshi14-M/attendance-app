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
}
