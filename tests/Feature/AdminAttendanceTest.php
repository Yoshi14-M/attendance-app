<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test*/
    public function 管理者は申請を承認できる(): void
    {
        $admin = User::factory()->create(['admin_status' => true]);
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '19:00:00',
            'comment' => '残業のため',
            'approval_status' => '承認待ち',
        ]);

        $this->actingAs($admin)->post("/stamp_correction_request/approve/{$application->id}");

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'approval_status' => '承認済み',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_out' => '19:00:00',
        ]);
    }

    /** @test */
    public function 一般ユーザーは承認画面にアクセスできない(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $record = AttendanceRecord::factory()->for($other)->create();
        $application = Application::factory()->create([
            'user_id' => $other->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
        ]);

        $response = $this->actingAs($user)->get("/stamp_correction_request/approve/{$application->id}");

        $response->assertForbidden();
    }

    /** @test */
    public function 管理者は当日の全ユーザー勤怠を閲覧できる(): void
    {
        $admin = User::factory()->create(['admin_status' => true]);
        $user = User::factory()->create();
        AttendanceRecord::factory()->for($user)->create(['date' => today()]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');

        $response->assertOk();
        $response->assertSee($user->name);
    }

    /** @test */
    public function 一般ユーザーは管理者の勤怠一覧にアクセスできない(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/attendance/list');

        $response->assertForbidden();
    }

    /** @test */
    public function 管理者は勤怠を直接修正できる(): void
    {
        $admin = User::factory()->create(['admin_status' => true]);
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($admin)->post("/attendance/{$record->id}", [
            'new_clock_in' => '09:00',
            'new_clock_out' => '19:00',
            'comment' => '残業のため直接修正',
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_out' => '19:00:00',
        ]);
        // 管理者の直接修正は申請テーブルを経由しないことも確認
        $this->assertDatabaseMissing('applications', [
            'attendance_record_id' => $record->id,
        ]);
    }
}
