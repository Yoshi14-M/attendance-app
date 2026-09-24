<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| 管理者ユーザー用ルート
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // 管理者 ゲスト専用ルート (ログイン前)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    // 管理者 認証必須ルート (ログイン後)
    Route::middleware(['auth', 'admin'])->group(function () {
        // 管理者ログアウト [FN017]
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー用ルート
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.admin'])->group(function () {
    // 打刻機能
    Route::get('/attendance', [AttendanceRecordController::class, 'create']);
    Route::post('/attendance', [AttendanceRecordController::class, 'store']);
    // 勤怠一覧表示
    Route::get('/attendance/list', [AttendanceRecordController::class, 'index']);
    // 勤怠詳細表示
    Route::get('/attendance/{id}', [AttendanceRecordController::class, 'show']);
});