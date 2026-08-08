<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * There is no self-registration for the platform operator account — it only
 * ever lives in the central database, and the central DB starts empty.
 * This is how the first (and any subsequent) superadmin login gets created.
 */
class CreateSuperadmin extends Command
{
    protected $signature = 'superadmin:create
        {email : Login email}
        {--name=Superadmin : Display name}
        {--password= : Password; generated and printed if omitted}';

    protected $description = 'Create a platform operator (superadmin) login';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("A central user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? Str::password(16);

        User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('Superadmin created.');
        $this->line("  URL:      /superadmin/login");
        $this->line("  Email:    {$email}");
        $this->line("  Password: {$password}");

        return self::SUCCESS;
    }
}
