<?php

use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\StaffController;
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
        // 勤怠一覧表示
        Route::get('/attendance/list', [AdminAttendanceController::class, 'index']);
        // スタッフ一覧表示
        Route::get('/staff/list', [StaffController::class, 'index']);
        // 月次表示
        Route::get('/attendance/staff/{id}', [StaffController::class, 'show']);
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
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー・管理者の双方からアクセスされる共通パス
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // 勤怠詳細表示(一般ユーザーは自分の勤怠のみ、管理者は全ユーザーの勤怠を閲覧可)
    Route::get('/attendance/{id}', [AttendanceRecordController::class, 'show']);
    // 勤怠詳細の修正・修正申請（一般ユーザーは申請、管理者は直接修正）
    Route::post('/attendance/{id}', [AttendanceRecordController::class, 'update']);
});
