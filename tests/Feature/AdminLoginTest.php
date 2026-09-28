<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一般ユーザーは管理者ログインができない(): void
    {
        User::factory()->create([
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'admin_status' => false,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
    }

    /** @test */
    public function 管理者は管理者ログインできる(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/attendance/list');
        $this->assertAuthenticated();
    }
}
