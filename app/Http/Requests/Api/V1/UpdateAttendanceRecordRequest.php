<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 本人または管理者のみ更新を許可する。
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('attendanceRecord'));
    }

    /**
     * Get the validation rules that apply to the request.
     * 部分更新に対応するため、date / clock_in は送信された場合のみ検証する。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * カスタムエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }

    /**
     * DBの複合ユニーク制約・出退勤の前後関係を検証する。
     * 未送信の項目は既存の値で補って比較する（clock_out だけの部分更新でも既存の clock_in と比較する）。
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var AttendanceRecord|null $attendanceRecord */
            $attendanceRecord = $this->route('attendanceRecord');

            if ($this->filled('date') && ! $validator->errors()->has('date')) {
                $exists = AttendanceRecord::where('user_id', $attendanceRecord?->user_id)
                    ->whereDate('date', $this->input('date'))
                    ->where('id', '!=', $attendanceRecord?->id) // 自身を除外
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('date', 'この日付の勤怠は既に登録されています。');
                }
            }

            if ($validator->errors()->hasAny(['clock_in', 'clock_out'])) {
                return;
            }

            $clockIn = $this->has('clock_in') ? $this->input('clock_in') : $attendanceRecord?->clock_in;
            $clockOut = $this->has('clock_out') ? $this->input('clock_out') : $attendanceRecord?->clock_out;

            // "H:i:s" 形式同士なので文字列比較で前後関係を判定できる
            if ($clockIn && $clockOut && $clockOut <= $clockIn) {
                $validator->errors()->add('clock_out', '退勤時刻は出勤時刻より後の時刻を指定してください。');
            }
        });
    }
}
