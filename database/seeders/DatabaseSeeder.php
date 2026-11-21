<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * The single account used to log in. Email, password and name come from .env
     * (ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NAME), never from the code.
     * Without a password in .env a random one is made once and printed. Safe to run again.
     */
    public function run(): void
    {
        $email = (string) config('learning.admin.email');
        $password = config('learning.admin.password');
        $name = (string) config('learning.admin.name');
        $user = User::where('email', $email)->first();

        if ($user && blank($password)) {
            return;
        }

        if (blank($password)) {
            $password = Str::password(16, symbols: false);
            $this->command?->warn("Admin account: {$email} / {$password}  (save the password, it is not shown again)");
        }

        User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
    }
}
