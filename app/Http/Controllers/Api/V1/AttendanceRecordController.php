<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     * 勤怠一覧画面の表示（一般ユーザー）
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => [], 'meta' => []]);
    }

    /**
     * Store a newly created resource in storage.
     * 勤怠登録（DBへの保存）
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([], 501);
    }

    /**
     * Display the specified resource.
     * 勤怠詳細画面の表示
     * （管理者かどうかで、表示する内容とビューを分岐）
     */
    public function show(AttendanceRecord $attendanceRecord): JsonResponse
    {
        return response()->json(['data' => $attendanceRecord]);
    }

    /**
     * Update the specified resource in storage.
     * 勤怠修正（一般ユーザーは修正申請、管理者は直接修正）
     */
    public function update(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        return response()->json([], 501);
    }

    /**
     * Remove the specified resource from storage.
     * 勤怠登録を削除する。
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        return response()->json([], 501);
    }
}
