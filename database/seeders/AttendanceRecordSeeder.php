<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AttendanceRecordSeeder extends Seeder
{
    /** 通常勤務（9:00-18:00） */
    private const NORMAL = ['09:00:00', '18:00:00'];

    /** 固定休憩（12:00-13:00） */
    private const LUNCH_BREAK = ['12:00:00', '13:00:00'];

    /**
     * Run the database seeds.
     * 全ユーザー（一般・管理者）に勤怠と休憩のダミーデータを作成する。
     */
    public function run(): void
    {
        $this->seedUser1(User::where('email', 'user1@example.com')->firstOrFail());

        // user1 以外は、過去5ヶ月＋当月に平日の通常勤務を作成する
        User::where('email', '!=', 'user1@example.com')
            ->get()
            ->each(fn (User $user) => $this->seedRegularUser($user));
    }

    /**
     * ★ 応用機能（マイ勤怠レポート）検証用に、意図的なパターンを持つダミーデータを作成する
     * 過去5ヶ月: 各月平日15日の通常勤務 / 当月: 通常10・残業3・遅刻2・早退1・長時間労働1 = 17日
     */
    private function seedUser1(User $user): void
    {
        collect(range(5, 1))
            ->flatMap(fn (int $monthsAgo) => $this->weekdaysOfMonth(Carbon::now()->startOfMonth()->subMonths($monthsAgo), 15))
            ->each(fn (Carbon $day) => $this->createRecord($user, $day, self::NORMAL));

        $patterns = collect()
            ->pad(10, self::NORMAL)
            ->merge(array_fill(0, 3, ['09:00:00', '20:00:00']))
            ->merge(array_fill(0, 2, ['09:30:00', '18:00:00']))
            ->merge([['09:00:00', '17:00:00']])
            ->merge([['08:00:00', '21:00:00']]);

        $this->weekdaysOfMonth(Carbon::now(), $patterns->count())
            ->each(fn (Carbon $day, int $index) => $this->createRecord($user, $day, $patterns[$index]));
    }

    /**
     * 一般的な打刻データ
     * （過去5ヶ月は各月平日15日、当月は昨日までの平日から最大10日の通常勤務）
     */
    private function seedRegularUser(User $user): void
    {
        collect(range(5, 1))
            ->flatMap(fn (int $monthsAgo) => $this->weekdaysOfMonth(Carbon::now()->startOfMonth()->subMonths($monthsAgo), 15))
            ->merge($this->weekdaysOfMonth(Carbon::now(), 10)->filter(fn (Carbon $day) => $day->isPast()))
            ->each(fn (Carbon $day) => $this->createRecord($user, $day, self::NORMAL));
    }

    /**
     * 指定した月の平日を、1日から順に指定件数だけ取得する。
     * 当日は打刻の動作確認ができるよう除外する（当月は当日以降の日付も含めて件数を揃える）。
     *
     * @return Collection<int, Carbon>
     */
    private function weekdaysOfMonth(Carbon $month, int $count): Collection
    {
        $start = $month->copy()->startOfMonth();

        return collect(range(0, $start->daysInMonth - 1))
            ->map(fn (int $offset) => $start->copy()->addDays($offset))
            ->reject(fn (Carbon $day) => $day->isWeekend() || $day->isToday())
            ->take($count)
            ->values();
    }

    /**
     * 指定したユーザー・日付・出退勤時刻で勤怠レコードを作成（既にあれば上書き）し、
     * 紐づく休憩レコードは固定休憩1件で作り直す。
     *
     * @param  array{0: string, 1: string}  $clockTimes  [出勤時刻, 退勤時刻]
     */
    private function createRecord(User $user, Carbon $date, array $clockTimes): void
    {
        [$clockIn, $clockOut] = $clockTimes;

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->toDateString()],
            ['clock_in' => $clockIn, 'clock_out' => $clockOut]
        );

        $record->breaks()->delete();
        $record->breaks()->create([
            'break_in' => self::LUNCH_BREAK[0],
            'break_out' => self::LUNCH_BREAK[1],
        ]);
    }
}
