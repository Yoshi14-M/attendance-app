<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_FOUND = ['error' => '勤怠情報が見つかりませんでした。'];

    private const FORBIDDEN = ['error' => 'この操作を実行する権限がありません。'];

    /** @test */
    public function 未認証の書き込みは401の_jso_nが返る(): void
    {
        $this->postJson('/api/v1/attendance-records', [])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function 存在しない_i_dの詳細取得は404の_jso_nが返る(): void
    {
        $this->getJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND);
    }

    /** @test */
    public function 存在しない_i_dの更新は404の_jso_nが返る(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/attendance-records/99999', ['clock_out' => '18:00:00'])
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND);
    }

    /** @test */
    public function 存在しない_i_dの削除は404の_jso_nが返る(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(self::NOT_FOUND);
    }

    /** @test */
    public function 他ユーザーの勤怠は更新できず403の_jso_nが返る(): void
    {
        $owner = User::factory()->create();
        $record = AttendanceRecord::factory()->for($owner)->create(['clock_out' => '18:00:00']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/attendance-records/{$record->id}", ['clock_out' => '19:00:00'])
            ->assertForbidden()
            ->assertExactJson(self::FORBIDDEN);

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'clock_out' => '18:00:00']);
    }

    /** @test */
    public function 他ユーザーの勤怠に不正なデータを送っても422ではなく403になる(): void
    {
        $record = AttendanceRecord::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/attendance-records/{$record->id}", ['clock_out' => 'invalid'])
            ->assertForbidden();
    }

    /** @test */
    public function 他ユーザーの勤怠は削除できず403の_jso_nが返る(): void
    {
        $record = AttendanceRecord::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")
            ->assertForbidden()
            ->assertExactJson(self::FORBIDDEN);

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id]);
    }

    /** @test */
    public function 管理者は他ユーザーの勤怠を更新できる(): void
    {
        $record = AttendanceRecord::factory()->create();
        Sanctum::actingAs(User::factory()->create(['admin_status' => true]));

        $this->putJson("/api/v1/attendance-records/{$record->id}", ['clock_out' => '19:00:00'])
            ->assertOk();

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'clock_out' => '19:00:00']);
    }

    /** @test */
    public function 管理者は他ユーザーの勤怠を削除できる(): void
    {
        $record = AttendanceRecord::factory()->create();
        Sanctum::actingAs(User::factory()->create(['admin_status' => true]));

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }
}
