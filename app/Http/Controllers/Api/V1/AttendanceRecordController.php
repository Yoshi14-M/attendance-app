<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     * 勤怠一覧の取得（認証不要）
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        // ページネーション
        $perPage = min((int) $request->input('per_page', 20), 100);

        // user_id・date・monthでの絞り込み
        $records = AttendanceRecord::with('user', 'breaks')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->input('user_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('date', $request->input('date')))
            ->when($request->filled('month'), function ($query) use ($request) {
                $month = $request->date('month');
                $query->whereYear('date', $month->year)->whereMonth('date', $month->month);
            })
            ->latest('date')
            ->paginate($perPage);

        return AttendanceRecordResource::collection($records);
    }

    /**
     * Store a newly created resource in storage.
     * 勤怠登録（Sanctum認証必須）
     * （認証ユーザー自身のレコードとして作成する）
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());
        $attendanceRecord->load('user', 'breaks');

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     * 勤怠詳細の習得（認証不要）
     * （ユーザー・休憩・修正申請を含めて返す）
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load('user', 'breaks', 'applications');

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Update the specified resource in storage.
     * 勤怠更新する（Sanctum認証必須）
     * 本人または管理者かどうかの認可は UpdateAttendanceRecordRequest::authorize() で
     * バリデーションより先に行う（他ユーザーの勤怠には422ではなく403を返すため）。
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->update($request->validated());
        $attendanceRecord->load('user', 'breaks');

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * Remove the specified resource from storage.
     * 勤怠を削除する（Sanctum認証必須。本人または管理者のみ）。
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
