<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing users and tokens
        DB::table('personal_access_tokens')->delete();
        DB::table('users')->delete();

        // Create single stable admin account
        User::create([
            'name' => 'Site Administrator',
            'email' => 'griffinsgri4@gmail.com',
            'password' => Hash::make('admin123@.'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }
}