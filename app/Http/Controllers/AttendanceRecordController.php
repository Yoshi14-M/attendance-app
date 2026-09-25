<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     * 勤怠一覧画面の表示（一般ユーザー）
     */
    public function index(Request $request)
    {
        $user = Auth::user();
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

                return $this->formatRecordForList($day, $record);
            });

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
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
     * 勤怠登録（DBへの保存）
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i:s');

        $attendanceRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendanceRecord) {
            $attendanceRecord = AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => $today,
            ]);
        }

        switch ($request->input('action')) {
            case 'clock_in':
                if (! $attendanceRecord->clock_in) {
                    $attendanceRecord->update(['clock_in' => $now]);
                }
                break;

            case 'clock_out':
                if ($attendanceRecord->clock_in && ! $attendanceRecord->clock_out) {
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
     * 勤怠詳細画面の表示
     */
    public function show(int $id)
    {
        $attendanceRecord = AttendanceRecord::with('breaks', 'applications')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return view('user.user-detail', [
            'user' => Auth::user(),
            'data' => $this->formatRecordForDetail($attendanceRecord),
        ]);

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
     * 勤怠修正（一般ユーザーは修正申請、管理者は直接修正）
     */
    public function update(UpdateAttendanceRequest $request, int $id)
    {
        $attendanceRecord = AttendanceRecord::findOrFail($id);
        $user = Auth::user();

        if ($user->admin_status) {
            $attendanceRecord->update([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $this->syncBreaks($attendanceRecord, $request->input('new_break_in', []), $request->input('new_break_out', []));

            return redirect('/admin/attendance/list');
        }

        abort_if($attendanceRecord->user_id !== $user->id, 403);
        abort_if($attendanceRecord->pendingApplication() !== null, 403, '承認待ちのため修正はできません。');

        $application = Application::create([
            'user_id' => $user->id,
            'attendance_record_id' => $attendanceRecord->id,
            'new_date' => $attendanceRecord->date,
            'new_clock_in' => $request->input('new_clock_in'),
            'new_clock_out' => $request->input('new_clock_out'),
            'comment' => $request->input('comment'),
            'application_date' => Carbon::now(),
        ]);

        collect($request->input('new_break_in', []))
            ->each(function (?string $breakIn, int $index) use ($application, $request) {
                $breakOut = $request->input('new_break_out')[$index] ?? null;

                if (! $breakIn && ! $breakOut) {
                    return;
                }

                ProposalBreak::create([
                    'application_id' => $application->id,
                    'break_in' => $breakIn,
                    'break_out' => $breakOut,
                ]);
            });

        return redirect('/attendance/list');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceRecord $attendanceRecord)
    {
        //
    }

    /**
     * 勤怠一覧の1行分の表示データを組み立てる
     */
    private function formatRecordForList(Carbon $day, ?AttendanceRecord $record): array
    {
        return [
            'id' => $record?->id,
            'date' => $day->locale('ja')->isoFormat('MM/DD(ddd)'),
            'clock_in' => $record?->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
            'clock_out' => $record?->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
            'total_break_time' => $record?->total_break_time,
            'total_time' => $record?->total_time,
        ];
    }

    /**
     * 勤怠詳細画面の表示データを組み立てる
     */
    private function formatRecordForDetail(AttendanceRecord $attendanceRecord): array
    {
        return [
            'id' => $attendanceRecord->id,
            'year' => $attendanceRecord->date->format('Y年'),
            'date' => $attendanceRecord->date->format('n月j日'),
            'clock_in' => $attendanceRecord->clock_in ? Carbon::parse($attendanceRecord->clock_in)->format('H:i') : '',
            'clock_out' => $attendanceRecord->clock_out ? Carbon::parse($attendanceRecord->clock_out)->format('H:i') : '',
            'breaks' => $attendanceRecord->breaks->map(fn (AttendanceBreak $break) => [
                'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
            ])->all(),
            'comment' => $attendanceRecord->comment,
            'application' => $attendanceRecord->pendingApplication(),
        ];
    }

    /**
     * 送信された休憩の入力内容で、勤怠に紐づく休憩レコードを作り直す
     *
     * @param  array<int, string|null>  $breakIns
     * @param  array<int, string|null>  $breakOuts
     */
    private function syncBreaks(AttendanceRecord $attendanceRecord, array $breakIns, array $breakOuts): void
    {
        $attendanceRecord->breaks()->delete();

        collect($breakIns)->each(function (?string $breakIn, int $index) use ($attendanceRecord, $breakOuts) {
            $breakOut = $breakOuts[$index] ?? null;

            if (! $breakIn && ! $breakOut) {
                return;
            }

            AttendanceBreak::create([
                'attendance_record_id' => $attendanceRecord->id,
                'break_in' => $breakIn,
                'break_out' => $breakOut,
            ]);
        });
    }
}
