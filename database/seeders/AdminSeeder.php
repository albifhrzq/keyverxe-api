<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin Keyverxe',
            'email' => 'admin@keyverxe.com',
            'password' => 'password',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }
}
