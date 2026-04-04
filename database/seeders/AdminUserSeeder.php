<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::updateOrCreate(
            ['email' => 'admin@okelcor.de'],
            [
                'name'     => 'Okelcor Admin',
                'password' => Hash::make('password123'),
                'role'     => 'super_admin',
            ]
        );

        AdminUser::updateOrCreate(
            ['email' => 'editor@okelcor.de'],
            [
                'name'     => 'Content Editor',
                'password' => Hash::make('password123'),
                'role'     => 'editor',
            ]
        );
    }
}
