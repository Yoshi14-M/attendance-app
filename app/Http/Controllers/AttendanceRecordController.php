<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     * 勤怠一覧画面の表示（一般ユーザー）
     */
    public function index(Request $request): View
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
    public function create(): View
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
     * 打刻処理（出勤・休憩入・休憩戻・退勤）
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $status = $user->attendance_status;
        $now = Carbon::now()->format('H:i:s');

        $todayRecord = fn (): ?AttendanceRecord => $user->attendanceRecords()
            ->whereDate('date', Carbon::today())
            ->first();

        match ($request->input('action')) {
            'clock_in' => $status === '勤務外'
                ? AttendanceRecord::updateOrCreate(
                    ['user_id' => $user->id, 'date' => Carbon::today()->toDateString()],
                    ['clock_in' => $now]
                )
                : null,
            'break_in' => $status === '出勤中'
                ? $todayRecord()->breaks()->create(['break_in' => $now])
                : null,
            'break_out' => $status === '休憩中'
                ? $todayRecord()->breaks()->whereNull('break_out')->latest('id')->first()->update(['break_out' => $now])
                : null,
            'clock_out' => $status === '出勤中'
                ? $todayRecord()->update(['clock_out' => $now])
                : null,
            default => null,
        };

        return redirect('/attendance');
    }

    /**
     * Display the specified resource.
     * 勤怠詳細画面の表示
     * （管理者かどうかで、表示する内容とビューを分岐）
     */
    public function show(int $id): View
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $attendanceRecord = AttendanceRecord::with('user', 'breaks')->findOrFail($id);

            return view('admin.admin-detail', [
                'user' => $attendanceRecord->user,
                'attendanceRecord' => $this->formatRecordForAdminDetail($attendanceRecord),
            ]);
        }

        $attendanceRecord = AttendanceRecord::with('breaks', 'applications')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return view('user.user-detail', [
            'user' => $user,
            'data' => $this->formatRecordForDetail($attendanceRecord),
        ]);

    }

    /**
     * Update the specified resource in storage.
     * 勤怠修正（一般ユーザーは修正申請、管理者は直接修正）
     */
    public function update(UpdateAttendanceRequest $request, int $id): RedirectResponse
    {
        $attendanceRecord = AttendanceRecord::findOrFail($id);
        $user = Auth::user();

        abort_if(! $user->admin_status && $attendanceRecord->user_id !== $user->id, 403);

        if ($attendanceRecord->pendingApplication() !== null) {
            return back()
                ->withInput()
                ->withErrors(['comment' => '承認待ちのため修正はできません。']);
        }

        if ($user->admin_status) {
            $attendanceRecord->update([
                'clock_in' => $this->toTimeString($request->input('new_clock_in')),
                'clock_out' => $this->toTimeString($request->input('new_clock_out')),
                'comment' => $request->input('comment'),
            ]);

            $this->syncBreaks($attendanceRecord, $request->input('new_break_in', []), $request->input('new_break_out', []));

            return redirect('/admin/attendance/list');
        }

        $application = Application::create([
            'user_id' => $user->id,
            'attendance_record_id' => $attendanceRecord->id,
            'new_date' => $attendanceRecord->date,
            'new_clock_in' => $this->toTimeString($request->input('new_clock_in')),
            'new_clock_out' => $this->toTimeString($request->input('new_clock_out')),
            'comment' => $request->input('comment'),
            'application_date' => Carbon::now(),
        ]);

        $this->breakPairs($request->input('new_break_in', []), $request->input('new_break_out', []))
            ->each(fn (array $break) => ProposalBreak::create([
                'application_id' => $application->id,
                ...$break,
            ]));

        return redirect('/attendance/list');
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
     * 勤怠詳細画面(一般ユーザー)の表示データを組み立てる
     */
    private function formatRecordForDetail(AttendanceRecord $attendanceRecord): array
    {
        return [
            ...$this->formatRecordForAdminDetail($attendanceRecord),
            'application' => $attendanceRecord->pendingApplication(),
        ];
    }

    /**
     * 勤怠詳細画面（管理者）の表示データを組み立てる
     */
    private function formatRecordForAdminDetail(AttendanceRecord $attendanceRecord): array
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

        $this->breakPairs($breakIns, $breakOuts)
            ->each(fn (array $break) => $attendanceRecord->breaks()->create($break));
    }

    /**
     * 休憩開始・終了の入力配列を、空行を除いた [break_in, break_out] の組に変換する
     *
     * @param  array<int, string|null>  $breakIns
     * @param  array<int, string|null>  $breakOuts
     * @return Collection<int, array{break_in: string|null, break_out: string|null}>
     */
    private function breakPairs(array $breakIns, array $breakOuts): Collection
    {
        return collect($breakIns)
            ->map(fn (?string $breakIn, int $index) => [
                'break_in' => $this->toTimeString($breakIn),
                'break_out' => $this->toTimeString($breakOuts[$index] ?? null),
            ])
            ->reject(fn (array $break) => $break['break_in'] === null && $break['break_out'] === null)
            ->values();
    }

    /**
     * "H:i" 形式の入力値を、DB保存用の "H:i:s" 形式に揃える
     */
    private function toTimeString(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('H:i:s') : null;
    }
}
