<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = config('kuyumcu.admin');

        if (blank($admin['password'])) {
            throw new RuntimeException('.env dosyasında ADMIN_PASSWORD tanımlı değil.');
        }

        // İlk yönetici hesabı; zaten varsa şifresine dokunulmaz.
        User::firstOrCreate(
            ['username' => $admin['username']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
