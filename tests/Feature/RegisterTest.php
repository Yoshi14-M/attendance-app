<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 名前が空白の場合バリデーションエラー(): void
    {
        $response = $this->post('/register', [
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
    }

    /** @test */
    public function test_email_required(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
    }

    /** @test */
    public function パスワードが8文字以下の場合バリデーションエラー(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
    }

    /** @test */
    public function パスワードが相違するとバリデーションエラー(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードと一致しません']);
    }

    /** @test */
    public function ユーザーの新規登録ができる(): void
    {
        $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'admin_status' => false,
        ]);
    }
}
