<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['username' => 'admin'],
            [
                'password_hash' => Hash::make('admin123'),
                'full_name' => 'System Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'is_active' => true,
                'deleted_at' => null,
            ]
        );

        $this->command?->info('Admin account restored: admin');
    }
}
