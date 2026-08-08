<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates, edits, or deletes a platform operator (superadmin) login. Lives
 * only in the central database — there is no "role" concept centrally, see
 * Superadmin\AuthController's doc comment. superadmin:create still covers
 * scripted/non-interactive first-run bootstrap; this covers everything
 * afterwards, the same split as tenant:setup vs tenant:user.
 */
class SuperadminUser extends Command
{
    protected $signature = 'superadmin:user';

    protected $description = 'Create, edit, or delete a platform operator (superadmin) login';

    public function handle(): int
    {
        $users = User::orderBy('name')->get();

        // '+' union, not [...spread, ...] — spread renumbers integer keys
        // sequentially from 0 when merged into an array literal (only
        // string keys survive it intact) — see tenant:user's identical note.
        $choice = select(
            label: 'Which superadmin?',
            options: $users->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} <{$u->email}>"])->all()
                + ['new' => '+ Create a new superadmin'],
        );

        if ($choice === 'new') {
            return $this->createUser();
        }

        $user = $users->firstWhere('id', $choice);

        $action = select(
            label: "What do you want to do with {$user->email}?",
            options: ['edit' => 'Edit', 'delete' => 'Delete'],
        );

        return $action === 'edit'
            ? $this->updateUser($user)
            : $this->deleteUser($user);
    }

    private function createUser(): int
    {
        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value) => User::where('email', $value)->exists()
                ? 'A central user with that email already exists.'
                : null,
        );

        $name = text(label: 'Display name', default: $email);

        [$password, $generated] = $this->choosePassword();

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('Superadmin created.');
        $this->line('  URL:   /superadmin/login');
        $this->line("  Email: {$email}");
        $this->reportPassword($password, $generated);

        return self::SUCCESS;
    }

    private function updateUser(User $user): int
    {
        $name = text(label: 'Display name', default: $user->name);

        $email = text(
            label: 'Email',
            default: $user->email,
            validate: fn (string $value) => User::where('email', $value)->where('id', '!=', $user->id)->exists()
                ? 'A central user with that email already exists.'
                : null,
        );

        $changePassword = confirm(
            label: "Change {$user->email}'s password?",
            default: false,
        );

        $user->update(['name' => $name, 'email' => $email]);

        $password = null;
        $generated = false;

        if ($changePassword) {
            [$password, $generated] = $this->choosePassword();
            $user->update(['password' => Hash::make($password)]);
        }

        $this->info('Superadmin updated.');
        $this->line("  Name:  {$name}");
        $this->line("  Email: {$email}");

        if ($password) {
            $this->reportPassword($password, $generated);
        }

        return self::SUCCESS;
    }

    private function deleteUser(User $user): int
    {
        // The last central login is the only way into /superadmin at all —
        // deleting it would need a fresh tinker session to recover from.
        if (User::count() === 1) {
            $this->error("\"{$user->email}\" is the only superadmin — deleting them would lock out the whole platform. Create another superadmin first.");

            return self::FAILURE;
        }

        if (! confirm(
            label: "Delete superadmin \"{$user->email}\"? This cannot be undone.",
            default: false,
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $user->delete();

        $this->info("Deleted superadmin \"{$user->email}\".");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: bool} [password, wasGenerated]
     */
    private function choosePassword(): array
    {
        $manual = confirm(
            label: 'Set a specific password? (No = generate a random one)',
            default: false,
        );

        return $manual
            ? [password(label: 'New password', required: true), false]
            : [Str::password(16), true];
    }

    private function reportPassword(string $password, bool $generated): void
    {
        if (! $generated) {
            $this->info('Password set.');

            return;
        }

        $this->line("  Password: {$password}");
        $this->warn('This password is not stored anywhere — save it now.');
    }
}
