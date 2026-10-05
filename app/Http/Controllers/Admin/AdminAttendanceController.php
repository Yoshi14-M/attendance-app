<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     * 勤怠一覧画面の表示（管理者）
     */
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->query('date', Carbon::today()->toDateString()));

        $users = User::where('admin_status', false)->get();
        $attendanceRecords = AttendanceRecord::with('breaks')
            ->whereDate('date', $date)
            ->whereIn('user_id', $users->pluck('id'))
            ->get();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => $users,
            'attendanceRecords' => $attendanceRecords,
        ]);
    }
}
