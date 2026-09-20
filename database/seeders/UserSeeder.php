<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ダミーユーザー１（一般）
        User::updateOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name' => '一般 ユーザー１',
                'password' => Hash::make('password'),
                'admin_status' => false,
                'email_verified_at' => now(),
            ]
        );

        // ダミーユーザー２（一般）
        User::updateOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name' => '一般 ユーザー２',
                'password' => Hash::make('password'),
                'admin_status' => false,
                'email_verified_at' => now(),
            ]
        );

        // ダミーユーザー３（管理者）
        User::updateOrCreate(
            ['email' => 'user3@example.com'],
            [
                'name' => '管理者 ユーザー',
                'password' => Hash::make('password'),
                'admin_status' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
