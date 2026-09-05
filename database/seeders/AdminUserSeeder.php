<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates (or updates) the one admin account that logs in to /admin.
     * There is no public registration, so this is the only way an admin
     * user gets created.
     *
     * Reads credentials from the environment so the same seeder is safe
     * to run locally and in production without editing code:
     *   ADMIN_NAME     -- defaults to Admin
     *   ADMIN_EMAIL    -- defaults to admin@example.com
     *   ADMIN_PASSWORD -- defaults to a random 16-char string, printed
     *                     to the console so it isn't lost if unset.
     *
     * Idempotent: running it again just resets the name/password for the
     * same email instead of creating duplicates.
     */
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'Admin');
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        $generated = false;

        if (! $password) {
            $password = Str::password(16);
            $generated = true;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Admin-konto klart: {$email}");

        if ($generated) {
            $this->command?->warn("Inget ADMIN_PASSWORD var satt -- genererat lösenord: {$password}");
            $this->command?->warn('Skriv ner det nu, det visas inte igen. Sätt ADMIN_PASSWORD för att styra det själv nästa gång.');
        }
    }
}
