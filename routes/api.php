<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::prefix('v1')->group(function () {
    // 勤怠一覧取得（認証不要）
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index']);
    // 勤怠詳細取得（認証不要）
    Route::get('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show']);

    // Sunctam認証ルート
    Route::middleware('auth:sanctum')->group(function () {
        // 勤怠登録
        Route::post('/attendance-records', [AttendanceRecordController::class, 'store']);
        // 勤怠更新（本人または管理者のみ）
        Route::match(['put', 'patch'], '/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        // 勤怠削除（本人または管理者のみ）
        Route::delete('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);
    });
});
