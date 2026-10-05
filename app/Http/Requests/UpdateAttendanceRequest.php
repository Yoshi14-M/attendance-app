<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['nullable', 'date_format:H:i'],
            'new_clock_out' => ['nullable', 'date_format:H:i'],
            'new_break_in' => ['array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * カスタムエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'comment.required' => '備考を記入してください',
        ];
    }

    /**
     * 出勤・退勤・休憩の時系列に矛盾がないかを検証する
     * （形式エラーがある場合は時系列の検証を行わない）
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $hasTimeFormatError = collect($validator->errors()->keys())
                ->contains(fn (string $key) => str_starts_with($key, 'new_'));

            if ($hasTimeFormatError) {
                return;
            }

            $clockIn = $this->toCarbon($this->input('new_clock_in'));
            $clockOut = $this->toCarbon($this->input('new_clock_out'));

            if ($clockIn && $clockOut && $clockOut->lt($clockIn)) {
                $validator->errors()->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');
            }

            $breakOuts = $this->input('new_break_out', []);

            collect($this->input('new_break_in', []))
                ->map(fn (?string $breakIn, int $index) => [
                    'index' => $index,
                    'in' => $this->toCarbon($breakIn),
                    'out' => $this->toCarbon($breakOuts[$index] ?? null),
                ])
                ->reject(fn (array $break) => $break['in'] === null && $break['out'] === null)
                ->each(function (array $break) use ($validator, $clockIn, $clockOut) {
                    $index = $break['index'];

                    // 休憩開始が未入力、出勤前・退勤後、または休憩終了より後
                    if (
                        $break['in'] === null
                        || ($clockIn && $break['in']->lt($clockIn))
                        || ($clockOut && $break['in']->gt($clockOut))
                        || ($break['out'] && $break['in']->gt($break['out']))
                    ) {
                        $validator->errors()->add("new_break_in.$index", '休憩時間が不適切な値です');
                    }

                    if ($break['out'] && $clockOut && $break['out']->gt($clockOut)) {
                        $validator->errors()->add("new_break_out.$index", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                });
        });
    }

    /**
     * "H:i" 形式の入力値を Carbon に変換する（未入力は null）
     */
    private function toCarbon(?string $time): ?Carbon
    {
        return $time ? Carbon::parse($time) : null;
    }
}
