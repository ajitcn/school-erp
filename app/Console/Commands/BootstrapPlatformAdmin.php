<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/** Create the first platform administrator through a private CLI prompt. */
#[Signature('platform:bootstrap-admin')]
#[Description('Create the first platform administrator account')]
class BootstrapPlatformAdmin extends Command
{
    /** Create the first platform administrator after migrations have run. */
    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_platform_super_admin')) {
            $this->error('Run the database migrations before creating the platform administrator.');

            return self::FAILURE;
        }

        if (User::query()->where('is_platform_super_admin', true)->exists()) {
            $this->error('A platform administrator already exists. This command can only bootstrap the first one.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $email = strtolower(trim((string) $this->ask('Administrator email')));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('Enter a name between 1 and 255 characters.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $this->error('Enter a valid email address no longer than 255 characters.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('That email address already belongs to an account.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (at least 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        if (mb_strlen($password) < 12 || $password !== $passwordConfirmation) {
            $this->error('The password must be at least 12 characters and both entries must match.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $password): void {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'is_platform_super_admin' => true,
            ])->save();
        });

        $this->info("Platform administrator {$email} created.");

        return self::SUCCESS;
    }
}
