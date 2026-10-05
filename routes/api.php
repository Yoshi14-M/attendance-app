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
    // 勤怠一覧表示
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index']);
    // 勤怠詳細表示
    Route::get('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        // 勤怠打刻
        Route::post('/attendance-records', [AttendanceRecordController::class, 'store']);
        // 勤怠修正申請
        Route::match(['put', 'patch'], '/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'update']);
        Route::delete('/attendance-records/{attendanceRecord}', [AttendanceRecordController::class, 'destroy']);
    });
});
