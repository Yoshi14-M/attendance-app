<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    /**
     * スタッフ一覧画面の表示
     */
    public function index()
    {
        return view('admin.staff-list', [
            'users' => User::where('admin_status', false)->get(),
        ]);
    }

    /**
     * スタッフ別の月次勤怠一覧画面の表示
     */
    public function show(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $date = Carbon::parse($request->query('date', Carbon::now()->format('Y-m')).'-01');

        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->get()
            ->keyBy(fn (AttendanceRecord $record) => $record->date->format('Y-m-d'));

        $formattedAttendanceRecords = collect(range(1, $date->daysInMonth))
            ->map(function (int $day) use ($date, $records) {
                $day = $date->copy()->day($day);
                $record = $records->get($day->format('Y-m-d'));

                return [
                    'id' => $record?->id,
                    'date' => $day->locale('ja')->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $record?->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    'clock_out' => $record?->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    'total_break_time' => $record?->total_break_time,
                    'total_time' => $record?->total_time,
                ];
            });

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

    /**
     * 指定ユーザー・指定月の勤怠情報をCSVでダウンロードする。
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        $user = User::findOrFail($request->integer('user_id'));
        $month = Carbon::parse($request->string('year_month').'-01');

        $records = $user->attendanceRecords()
            ->with('breaks')
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->orderBy('date')
            ->get();

        $fileName = sprintf('%s_%s_attendance.csv', $user->name, $month->format('Y-m'));

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);

            $records->each(function (AttendanceRecord $record) use ($handle): void {
                fputcsv($handle, [
                    $record->date->format('Y-m-d'),
                    $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    $record->total_break_time ? Carbon::parse($record->total_break_time)->format('G:i') : '',
                    $record->total_time ? Carbon::parse($record->total_time)->format('G:i') : '',
                ]);
            });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
