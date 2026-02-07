<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@zagadogs.test',
            ],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => Carbon::now(),
            ]
        );
    }
}
