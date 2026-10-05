<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 管理者は全操作を許可する。管理者以外は、下の個別メソッドの判定に進む。
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->admin_status ? true : null; // 管理者でない場合はfalseではなくnullを返す（全拒否を防ぐ）
    }

    /**
     * Determine whether the user can update the model.
     * 本人のみ更新できる（管理者は before() で許可済み）
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     * 本人のみ削除できる（管理者は before() で許可済み）
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
