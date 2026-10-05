<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    /**
     * 管理者ログイン画面を表示する
     * ログイン・ログアウト処理は Fortify の AuthenticatedSessionController が担う。
     */
    public function create(): View
    {
        return view('admin.admin-login');
    }
}
