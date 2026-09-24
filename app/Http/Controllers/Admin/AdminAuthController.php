<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    /**
     * 管理者ログイン画面を表示する
     */
    public function showLoginForm(): View
    {
        return view('admin.admin-login');
    }

    /**
     * 管理者ログイン処理を行う
     */
    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! $user->admin_status || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/admin/attendance/list');
    }

    /**
     * 管理者ログアウト処理を行う
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
