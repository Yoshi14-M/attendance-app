<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一覧取得は未認証でもアクセスでき200とページネーション情報が返る(): void
    {
        $user = User::factory()->create();
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-09-01']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-09-02']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-09-03']);

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    /** @test */
    public function user_idで絞り込める(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        AttendanceRecord::factory()->for($user1)->create();
        AttendanceRecord::factory()->for($user2)->create();

        $response = $this->getJson("/api/v1/attendance-records?user_id={$user1->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function 詳細取得でユーザー休憩修正申請を含めて取得できる(): void
    {
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create();
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $response = $this->getJson("/api/v1/attendance-records/{$record->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonCount(1, 'data.breaks');
    }

    /** @test */
    public function 登録は未認証だと401になる(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-09-01',
            'clock_in' => '09:00:00',
        ]);

        $response->assertUnauthorized();
    }

    /** @test */
    public function 認証済みなら勤怠を登録でき201が返る(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-09-01',
            'clock_in' => '09:00:00',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'clock_in' => '09:00:00',
        ]);

        $record = AttendanceRecord::where('user_id', $user->id)->first();
        $this->assertEquals('2026-09-01', $record->date->format('Y-m-d'));
    }

    /** @test */
    public function 不正データ送信時に422と日本語エラーメッセージが返る(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', []);

        $response->assertStatus(422);
        $response->assertJsonFragment(['勤怠日は必須です。']);
        $response->assertJsonFragment(['出勤時刻は必須です。']);
    }

    /** @test */
    public function 同じ日付への重複登録は422になる(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-09-01']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-09-01',
            'clock_in' => '09:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['この日付の勤怠は既に登録されています。']);
    }

    /** @test */
    public function 更新は未認証だと401になる(): void
    {
        $record = AttendanceRecord::factory()->create();

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_out' => '18:00:00',
        ]);

        $response->assertUnauthorized();
    }

    /** @test */
    public function 認証済みなら勤怠を更新でき200が返る(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user); // Sanctum認証された状態にする
        $record = AttendanceRecord::factory()->for($user)->create(['clock_in' => '09:00:00']);

        $response = $this->putJson("/api/v1/attendance-records/{$record->id}", [
            'clock_out' => '18:00:00',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_out' => '18:00:00',
        ]);
    }

    /** @test */
    public function 削除は未認証だと401になる(): void
    {
        $record = AttendanceRecord::factory()->create();

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")->assertUnauthorized();
    }

    /** @test */
    public function 認証済みなら勤怠を削除でき204が返る(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = AttendanceRecord::factory()->for($user)->create();

        $response = $this->deleteJson("/api/v1/attendance-records/{$record->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    /** @test */
    public function 部分更新では送信した項目だけが更新され未送信の項目は保持される(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = AttendanceRecord::factory()->for($user)->create([
            'date' => '2026-09-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '元の備考',
        ]);

        $this->patchJson("/api/v1/attendance-records/{$record->id}", ['clock_out' => '19:00:00'])
            ->assertOk()
            ->assertJsonPath('data.clock_out', '19:00:00');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
            'comment' => '元の備考',
        ]);
    }

    /** @test */
    public function 退勤時刻だけの部分更新でも既存の出勤時刻より前なら422になる(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = AttendanceRecord::factory()->for($user)->create(['clock_in' => '09:00:00', 'clock_out' => '18:00:00']);

        $this->patchJson("/api/v1/attendance-records/{$record->id}", ['clock_out' => '08:00:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clock_out' => '退勤時刻は出勤時刻より後の時刻を指定してください。']);

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'clock_out' => '18:00:00']);
    }

    /** @test */
    public function 出勤時刻だけの部分更新でも既存の退勤時刻より後なら422になる(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = AttendanceRecord::factory()->for($user)->create(['clock_in' => '09:00:00', 'clock_out' => '18:00:00']);

        $this->patchJson("/api/v1/attendance-records/{$record->id}", ['clock_in' => '19:00:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clock_out' => '退勤時刻は出勤時刻より後の時刻を指定してください。']);
    }

    /** @test */
    public function 更新時の日付重複は自身を除いて判定される(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = AttendanceRecord::factory()->for($user)->create(['date' => '2026-09-01']);
        AttendanceRecord::factory()->for($user)->create(['date' => '2026-09-02']);

        $this->patchJson("/api/v1/attendance-records/{$record->id}", ['date' => '2026-09-01'])->assertOk();
        $this->patchJson("/api/v1/attendance-records/{$record->id}", ['date' => '2026-09-02'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'この日付の勤怠は既に登録されています。']);
    }

    /** @test */
    public function acceptヘッダが無くても未認証の書き込みは401のjsonが返る(): void
    {
        $this->post('/api/v1/attendance-records', [])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
