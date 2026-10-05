<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理者はcsvをダウンロードできる(): void
    {
        $admin = User::factory()->create(['admin_status' => true]);
        $user = User::factory()->create();
        $record = AttendanceRecord::factory()->for($user)->create([
            'date' => '2026-09-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        $record->breaks()->create(['break_in' => '12:00:00', 'break_out' => '13:00:00']);

        $response = $this->actingAs($admin)->post('/export', [
            'user_id' => $user->id,
            'year_month' => '2026-09',
        ]);

        $response->assertOk();
        $response->assertHeader('content-disposition');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('2026-09-01', $csv);
        $this->assertStringContainsString('09:00', $csv);
        $this->assertStringContainsString('8:00', $csv); // 実働8時間
    }

    /** @test */
    public function 一般ユーザーはcsv出力にアクセスできない(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/export', [
            'user_id' => $user->id,
            'year_month' => '2026-09',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function 存在しないユーザーidはバリデーションエラーになる(): void
    {
        $admin = User::factory()->create(['admin_status' => true]);

        $response = $this->actingAs($admin)->post('/export', [
            'user_id' => 99999,
            'year_month' => '2026-09',
        ]);

        $response->assertSessionHasErrors('user_id');
    }
}
