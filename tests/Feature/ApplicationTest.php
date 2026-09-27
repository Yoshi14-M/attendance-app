<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一般ユーザーは自分の申請一覧のみ閲覧できる(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();
        Application::factory()->create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
            'comment' => '確認用',
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertOk();
        $response->assertSee('確認用');
    }

    /** @test */
    public function 他人の申請詳細リンクにはアクセスできない(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $record = AttendanceRecord::factory()->for($other)->create();
        $application = Application::factory()->create([
            'user_id' => $other->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
        ]);

        $response = $this->actingAs($user)->get("/application/{$application->id}");

        $response->assertNotFound();
    }
}
