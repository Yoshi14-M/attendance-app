<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Requests\LoginRequest as AppLoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
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
        // Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        // Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        // Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        // Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // 一般ユーザーのログイン画面ビューの設定
        Fortify::loginView(fn () => view('user.user-login'));
        Fortify::registerView(fn () => view('user.register'));

        // 一般ユーザーのログイン認証（管理者は対象外）
        Fortify::authenticateUsing(function (Request $request) {
            $loginRequest = new AppLoginRequest;

            Validator::make(
                $request->all(),
                $loginRequest->rules(),
                $loginRequest->messages()
            )->validate();

            $user = User::where('email', $request->email)
                ->where('admin_status', false)
                ->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });

        // 一般ユーザーのログアウト後のリダイレクト先の設定
        $this->app->singleton(LogoutResponse::class, function () {
            return new class implements LogoutResponse
            {
                public function toResponse($request)
                {
                    return redirect()->route('login');
                }
            };
        });
    }
}
