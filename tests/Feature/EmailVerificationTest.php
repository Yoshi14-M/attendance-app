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
    public function 未認証ユーザーは認証誘導画面に遷移する(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertRedirect(route('verification.notice'));
    }

    /** @test */
    public function 認証リンクをクリックするとメール認証が完了する(): void
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
