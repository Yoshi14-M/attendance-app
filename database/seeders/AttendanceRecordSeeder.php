<?php

namespace Database\Seeders;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 勤怠記録情報のダミーデータ
        $user1 = User::where('email', 'user1@example.com')->firstOrFail();
        $user2 = User::where('email', 'user2@example.com')->firstOrFail();

        $this->seedUser1($user1);
        $this->seedUser2($user2);
    }

    /**
     * ★ 応用機能（マイ勤怠レポート）検証用に、意図的なパターンを持つダミーデータを作成する
     */
    private function seedUser1(User $user): void
    {
        // 過去5ヶ月：各月 平日15日、9:00-18:00、休憩12:00-13:00固定
        for ($monthsAgo = 5; $monthsAgo >= 1; $monthsAgo--) {
            $month = Carbon::now()->subMonths($monthsAgo)->startOfMonth();
            $weekdays = $this->weekdaysOfMonth($month, 15);

            foreach ($weekdays as $day) {
                $this->createRecord($user, $day, '09:00:00', '18:00:00', [['12:00:00', '13:00:00']]);
            }
        }

        // 当月：通常10 / 残業3 / 遅刻2 / 早退1 / 長時間労働1 = 計17日
        $currentMonth = Carbon::now()->startOfMonth();
        $weekdays = $this->weekdaysOfMonth($currentMonth, 17);
        $patterns = array_merge(
            array_fill(0, 10, ['09:00:00', '18:00:00']),
            array_fill(0, 3, ['09:00:00', '20:00:00']),
            array_fill(0, 2, ['09:30:00', '18:00:00']),
            array_fill(0, 1, ['09:00:00', '17:00:00']),
            array_fill(0, 1, ['08:00:00', '21:00:00']),
        );

        foreach ($weekdays as $index => $day) {
            [$clockIn, $clockOut] = $patterns[$index];
            $this->createRecord($user, $day, $clockIn, $clockOut, [['12:00:00', '13:00:00']]);
        }
    }

    /**
     * 一般的な打刻データ（比較用のもう一人のユーザー）
     */
    private function seedUser2(User $user): void
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $weekdays = $this->weekdaysOfMonth($currentMonth, 12);

        foreach ($weekdays as $day) {
            $this->createRecord($user, $day, '09:00:00', '18:00:00', [['12:00:00', '13:00:00']]);
        }
    }

    /**
     * 指定した月の平日を先頭から指定件数だけ取得する
     * ある月の1日から末日（または昨日、早い方）までを1日ずつ確認し、
     * 土日を除いた平日を、指定件数集まるまで拾い集める。
     *
     * @return array<int, Carbon>
     */
    private function weekdaysOfMonth(Carbon $month, int $count): array
    {
        $days = [];
        $cursor = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->min(Carbon::yesterday());

        while ($cursor->lte($end) && count($days) < $count) {
            if (! $cursor->isWeekend()) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * 指定したユーザー・日付・出退勤時刻で勤怠レコードを作成（既にあれば上書き）し、
     * 紐づく休憩レコードは一旦全部削除してから、引数で渡された休憩時間の分だけ作り直す。
     *
     * @param  array<int, array{0: string, 1: string}>  $breaks
     */
    private function createRecord(User $user, Carbon $date, string $clockIn, string $clockOut, array $breaks): void
    {
        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            ['clock_in' => $clockIn, 'clock_out' => $clockOut]
        );

        $record->breaks()->delete();

        foreach ($breaks as [$breakIn, $breakOut]) {
            AttendanceBreak::create([
                'attendance_record_id' => $record->id,
                'break_in' => $breakIn,
                'break_out' => $breakOut,
            ]);
        }
    }
}
