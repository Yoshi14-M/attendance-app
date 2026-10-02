<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一覧取得は未認証でもアクセスできる(): void
    {
        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertOk();
    }

    /** @test */
    public function 書き込み系は未認証だと401になる(): void
    {
        $this->postJson('/api/v1/attendance-records')->assertUnauthorized();
    }

    /** @test */
    public function 書き込み系は_sanctumトークンがあれば認証を通過する(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance-records');

        // ロジック未実装のため501（認証自体は通過している）
        $response->assertStatus(501);
    }
}
