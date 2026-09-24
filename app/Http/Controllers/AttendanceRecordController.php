<?php

namespace App\Http\Controllers;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     * 勤怠一覧画面の表示（一般ユーザー）
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     * 勤怠登録（打刻）画面の表示
     */
    public function create()
    {
        $now = Carbon::now();

        return view('user.attendance-register', [
            'user' => Auth::user(),
            'formattedDate' => $now->locale('ja')->isoFormat('YYYY年M月D日(ddd)'),
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * 勤怠登録（AttendanceRecordテーブルへの保存）
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i:s');

        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendanceRecord) {
            $attendanceRecord = AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => $today,
            ]);
        }

        switch ($request->input('action')) {
            case 'clock_in':
                if (!$attendanceRecord->clock_in) {
                    $attendanceRecord->update(['clock_in' => $now]);
                }
                break;

            case 'clock_out':
                if ($attendanceRecord->clock_in && !$attendanceRecord->clock_out) {
                    $attendanceRecord->update(['clock_out' => $now]);
                }
                break;

            case 'break_in':
                AttendanceBreak::create([
                    'attendance_record_id' => $attendanceRecord->id,
                    'break_in' => $now,
                ]);
                break;

            case 'break_out':
                $openBreak = $attendanceRecord->breaks()->whereNull('break_out')->latest('id')->first();
                $openBreak?->update(['break_out' => $now]);
                break;
        }

        return redirect('/attendance');
    }

    /**
     * Display the specified resource.
     */
    public function show(AttendanceRecord $attendanceRecord)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AttendanceRecord $attendanceRecord)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttendanceRecord $attendanceRecord)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceRecord $attendanceRecord)
    {
        //
    }
}
