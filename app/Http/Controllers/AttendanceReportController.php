<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    /** 集計対象の月数（当月を含む） */
    private const REPORT_MONTHS = 6;

    /** 所定労働時間（秒）: 8時間。これを超えた分が残業 */
    private const STANDARD_WORK_SECONDS = 8 * 3600;

    /** 長時間労働の基準（秒）: 10時間を超えた日 */
    private const LONG_WORK_SECONDS = 10 * 3600;

    /** 始業・終業時刻（0:00からの秒数） */
    private const WORK_START_SECONDS = 9 * 3600;

    private const WORK_END_SECONDS = 18 * 3600;

    /**
     * マイ勤怠レポート画面を表示する。
     * 集計期間は「5ヶ月前の1日 〜 当月末日」。当月は月内発生回数として月末までを対象にする。
     */
    public function index(): View
    {
        $thisMonth = Carbon::today()->startOfMonth();
        $from = $thisMonth->copy()->subMonths(self::REPORT_MONTHS - 1);

        $records = Auth::user()->attendanceRecords()
            ->with('breaks')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $thisMonth->copy()->endOfMonth())
            ->get();

        // 出勤・退勤が揃っている日だけを、労働時間の集計対象にする
        $worked = $records
            ->filter(fn (AttendanceRecord $record): bool => $record->clock_in !== null && $record->clock_out !== null)
            ->map(fn (AttendanceRecord $record): array => $this->toWorkedDay($record))
            ->values();

        return view('reports.index', [
            'summary' => $this->buildSummary($worked),
            'monthlyTrend' => $this->buildMonthlyTrend($worked, $thisMonth),
            'anomalies' => $this->buildAnomalies($records, $worked, $thisMonth),
        ]);
    }

    /**
     * 1日分の勤怠を、集計用の配列（月・実働秒数・残業秒数）に変換する。
     *
     * @return array{month: string, work_seconds: int, overtime_seconds: int}
     */
    private function toWorkedDay(AttendanceRecord $record): array
    {
        $workSeconds = max(
            0,
            $this->secondsOf($record->clock_out) - $this->secondsOf($record->clock_in) - $record->total_break_seconds
        );

        return [
            'month' => $record->date->format('Y/m'),
            'work_seconds' => $workSeconds,
            'overtime_seconds' => max(0, $workSeconds - self::STANDARD_WORK_SECONDS),
        ];
    }

    /**
     * 総労働時間・総残業時間・1日あたり平均労働時間（いずれも分）を集計する。
     *
     * @param  Collection<int, array{month: string, work_seconds: int, overtime_seconds: int}>  $worked
     * @return array{total_work_minutes: int, total_overtime_minutes: int, avg_work_minutes: int}
     */
    private function buildSummary(Collection $worked): array
    {
        $totalWorkSeconds = (int) $worked->sum('work_seconds');

        return [
            'total_work_minutes' => $this->toMinutes($totalWorkSeconds),
            'total_overtime_minutes' => $this->toMinutes((int) $worked->sum('overtime_seconds')),
            'avg_work_minutes' => $worked->isEmpty()
                ? 0
                : $this->toMinutes(intdiv($totalWorkSeconds, $worked->count())),
        ];
    }

    /**
     * 過去6ヶ月分の月別労働時間・残業時間（分）を、古い月から順に返す。データが無い月は0。
     *
     * @param  Collection<int, array{month: string, work_seconds: int, overtime_seconds: int}>  $worked
     * @return array<int, array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    private function buildMonthlyTrend(Collection $worked, Carbon $thisMonth): array
    {
        $byMonth = $worked->groupBy('month');

        return collect(range(self::REPORT_MONTHS - 1, 0))
            ->map(function (int $monthsAgo) use ($thisMonth, $byMonth): array {
                $month = $thisMonth->copy()->subMonths($monthsAgo)->format('Y/m');
                $days = $byMonth->get($month, collect());

                return [
                    'month' => $month,
                    'work_minutes' => $this->toMinutes((int) $days->sum('work_seconds')),
                    'overtime_minutes' => $this->toMinutes((int) $days->sum('overtime_seconds')),
                ];
            })
            ->all();
    }

    /**
     * 当月の遅刻・早退・長時間労働の回数を集計する。
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  Collection<int, array{month: string, work_seconds: int, overtime_seconds: int}>  $worked
     * @return array{late_count: int, early_leave_count: int, long_work_count: int}
     */
    private function buildAnomalies(Collection $records, Collection $worked, Carbon $thisMonth): array
    {
        $currentMonth = $thisMonth->format('Y/m');

        $thisMonthRecords = $records
            ->filter(fn (AttendanceRecord $record): bool => $record->date->format('Y/m') === $currentMonth);

        return [
            'late_count' => $thisMonthRecords
                ->filter(fn (AttendanceRecord $record): bool => $record->clock_in !== null
                    && $this->secondsOf($record->clock_in) > self::WORK_START_SECONDS)
                ->count(),
            'early_leave_count' => $thisMonthRecords
                ->filter(fn (AttendanceRecord $record): bool => $record->clock_out !== null
                    && $this->secondsOf($record->clock_out) < self::WORK_END_SECONDS)
                ->count(),
            'long_work_count' => $worked
                ->where('month', $currentMonth)
                ->filter(fn (array $day): bool => $day['work_seconds'] > self::LONG_WORK_SECONDS)
                ->count(),
        ];
    }

    /**
     * "H:i:s" 形式の時刻を、0:00からの経過秒数に変換する。
     */
    private function secondsOf(string $time): int
    {
        return Carbon::parse($time)->secondsSinceMidnight();
    }

    /**
     * 秒を分（切り捨て）に変換する。
     */
    private function toMinutes(int $seconds): int
    {
        return intdiv($seconds, 60);
    }
}
