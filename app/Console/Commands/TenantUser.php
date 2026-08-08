<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates, edits, or deletes a user on an existing tenant. tenant:setup only
 * covers day-zero provisioning — this covers "client locked out", "add a
 * second staff login", "this person's name/email changed", and "this
 * person shouldn't have access anymore" afterwards.
 */
class TenantUser extends Command
{
    use SelectsTenant;

    protected $signature = 'tenant:user';

    protected $description = 'Create, edit, or delete a user on an existing tenant';

    public function handle(): int
    {
        $tenant = $this->selectTenant('Which tenant is this user on?');

        if (! $tenant) {
            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            return $this->manageUser($tenant);
        } finally {
            tenancy()->end();
        }
    }

    private function manageUser(Tenant $tenant): int
    {
        $users = User::orderBy('name')->get();

        // '+' union, not [...spread, ...] — spread renumbers integer keys
        // sequentially from 0 when merged into an array literal (only
        // string keys survive it intact), which would silently turn every
        // user's real id into its position in the list instead.
        $choice = select(
            label: 'Which user?',
            options: $users->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} <{$u->email}>"])->all()
                + ['new' => '+ Create a new user'],
        );

        if ($choice === 'new') {
            return $this->createUser($tenant);
        }

        $user = $users->firstWhere('id', $choice);

        $action = select(
            label: "What do you want to do with {$user->email}?",
            options: ['edit' => 'Edit', 'delete' => 'Delete'],
        );

        return $action === 'edit'
            ? $this->updateUser($tenant, $user)
            : $this->deleteUser($tenant, $user);
    }

    private function createUser(Tenant $tenant): int
    {
        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value) => User::where('email', $value)->exists()
                ? 'A user with that email already exists.'
                : null,
        );

        $name = text(label: 'Display name', default: $email);

        $role = select(
            label: 'Role',
            options: ['editor' => 'Editor', 'superadmin' => 'Superadmin'],
            default: 'editor',
        );

        [$password, $generated] = $this->choosePassword();

        Role::findOrCreate($role, 'web');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        $user->assignRole($role);

        $this->info("Created user on \"{$tenant->name}\".");
        $this->line("  Email: {$email}");
        $this->line("  Role:  {$role}");
        $this->reportPassword($password, $generated);

        return self::SUCCESS;
    }

    private function updateUser(Tenant $tenant, User $user): int
    {
        $name = text(label: 'Display name', default: $user->name);

        $email = text(
            label: 'Email',
            default: $user->email,
            validate: fn (string $value) => User::where('email', $value)->where('id', '!=', $user->id)->exists()
                ? 'A user with that email already exists.'
                : null,
        );

        $role = select(
            label: 'Role',
            options: ['editor' => 'Editor', 'superadmin' => 'Superadmin'],
            default: $user->role,
        );

        $changePassword = confirm(
            label: "Change {$user->email}'s password?",
            default: false,
        );

        Role::findOrCreate($role, 'web');

        $user->update(['name' => $name, 'email' => $email, 'role' => $role]);
        $user->syncRoles([$role]);

        $password = null;
        $generated = false;

        if ($changePassword) {
            [$password, $generated] = $this->choosePassword();
            $user->update(['password' => Hash::make($password)]);
        }

        $this->info("Updated user on \"{$tenant->name}\".");
        $this->line("  Name:  {$name}");
        $this->line("  Email: {$email}");
        $this->line("  Role:  {$role}");

        if ($password) {
            $this->reportPassword($password, $generated);
        }

        return self::SUCCESS;
    }

    private function deleteUser(Tenant $tenant, User $user): int
    {
        // The last login on a tenant is the only way back in — deleting it
        // would need a fresh tinker session to recover from, not a normal
        // "add a second staff login" undo.
        if (User::count() === 1) {
            $this->error("\"{$user->email}\" is the only user on \"{$tenant->name}\" — deleting them would lock the tenant out of its own admin. Create another user first.");

            return self::FAILURE;
        }

        if (! confirm(
            label: "Delete \"{$user->email}\" from \"{$tenant->name}\"? This cannot be undone.",
            default: false,
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $user->delete();

        $this->info("Deleted \"{$user->email}\" from \"{$tenant->name}\".");

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
