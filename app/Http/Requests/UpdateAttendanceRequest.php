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

    public function messages(): array
    {
        return [
            'comment.required' => '備考を記入してください',
        ];
    }

    /**
     * 出勤・退勤・休憩の時系列に矛盾がないかを検証する
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            if ($clockIn && $clockOut && Carbon::parse($clockOut)->lt(Carbon::parse($clockIn))) {
                $validator->errors()->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');
            }

            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (! $breakIn && ! $breakOut) {
                    continue;
                }

                if ($breakIn && $clockIn && Carbon::parse($breakIn)->lt(Carbon::parse($clockIn))) {
                    $validator->errors()->add("new_break_in.$index", '休憩時間が不適切な値です');
                }

                if ($breakIn && $clockOut && Carbon::parse($breakIn)->gt(Carbon::parse($clockOut))) {
                    $validator->errors()->add("new_break_in.$index", '休憩時間が不適切な値です');
                }

                if ($breakOut && $clockOut && Carbon::parse($breakOut)->gt(Carbon::parse($clockOut))) {
                    $validator->errors()->add("new_break_out.$index", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }
}
