<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 一覧API（index）と詳細API（show）で共用する。
     * breaks / user / applications は whenLoaded() により、
     * コントローラー側で with() した場合のみレスポンスに含まれる。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'date' => $this->date->format('Y-m-d'),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => $this->total_time,
            'total_break_time' => $this->total_break_time,
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }
}
