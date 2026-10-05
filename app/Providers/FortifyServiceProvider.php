<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\LoginRequest as AppLoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // 一般ユーザーのログイン画面ビューの設定
        Fortify::loginView(fn () => view('user.user-login'));
        Fortify::registerView(fn () => view('user.register'));

        // 一般ユーザーのログイン認証（/admin/login からの場合は管理者、それ以外は一般ユーザーのみを認証する）
        Fortify::authenticateUsing(function (Request $request): ?User {
            $isAdminLogin = $request->is('admin/*');
            $formRequest = $isAdminLogin ? new AdminLoginRequest : new AppLoginRequest;

            Validator::make(
                $request->all(),
                $formRequest->rules(),
                $formRequest->messages()
            )->validate();

            $user = User::where('email', $request->email)
                ->where('admin_status', $isAdminLogin)
                ->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });

        // ログイン後のリダイレクト先の設定（管理者は勤怠一覧、一般ユーザーは打刻画面）
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse
            {
                public function toResponse($request): RedirectResponse
                {
                    return $request->user()->admin_status
                        ? redirect('/admin/attendance/list')
                        : redirect()->intended(Fortify::redirects('login'));
                }
            };
        });

        // ログアウト後のリダイレクト先の設定（管理者は管理者ログイン画面、一般ユーザーはログイン画面）
        $this->app->singleton(LogoutResponse::class, function () {
            return new class implements LogoutResponse
            {
                public function toResponse($request): RedirectResponse
                {
                    return $request->is('admin/*')
                        ? redirect()->route('admin.login')
                        : redirect()->route('login');
                }
            };
        });
    }
}
