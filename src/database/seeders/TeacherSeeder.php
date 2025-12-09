<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        Teacher::create([
            'name' => 'Ichiki',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password'), // 本番環境では変更してください
        ]);
    }
}