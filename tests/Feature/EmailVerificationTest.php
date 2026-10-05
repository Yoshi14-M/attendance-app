<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 会員登録後に認証メールが送信される(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Notification::assertSentTo(User::where('email', 'taro@example.com')->first(), VerifyEmail::class);
    }

    /** @test */
    public function 未認証ユーザーは認証誘導画面に遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertRedirect(route('verification.notice'));
    }

    /** @test */
    public function 認証誘導画面の認証はこちらからボタンからメール認証サイトに遷移できる(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('<a class="verify__link" href="http://localhost:8025"', false);
        $response->assertSee('認証はこちらから');
    }

    /** @test */
    public function 認証リンクをクリックするとメール認証が完了し勤怠登録画面に遷移する(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($url);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $response->assertRedirect('/attendance?verified=1');
    }

    /** @test */
    public function 認証メールを再送できる(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
