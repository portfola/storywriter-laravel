<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UpdateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update
                            {user : ID or email address of the account to update}
                            {--name= : New display name}
                            {--email= : New email address, stored lowercased}
                            {--password= : New password; use --prompt-password to keep it out of your shell history}
                            {--prompt-password : Ask for the new password instead of passing it as an option}
                            {--admin : Give the account dashboard access}
                            {--no-admin : Take dashboard access away}
                            {--verified : Mark the email address verified}
                            {--unverified : Clear the email verification}
                            {--revoke-tokens : Delete the account\'s API tokens, forcing a re-login}
                            {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update an existing user account from the command line';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = $this->resolveUser($identifier);

        if (! $user) {
            $this->error("No account matches \"{$identifier}\". Try: php artisan users:list --search=...");

            return self::FAILURE;
        }

        if ($conflict = $this->conflictingOptions()) {
            $this->error($conflict);

            return self::FAILURE;
        }

        // Passing --password puts it in the shell history; prompting doesn't.
        $password = $this->option('prompt-password')
            ? $this->secret('New password')
            : $this->option('password');

        [$changes, $summary] = $this->collectChanges($user, $password);

        if ($changes === []) {
            $this->warn('Nothing to change. Pass at least one of --name, --email, --password, --admin/--no-admin, --verified/--unverified.');

            return self::FAILURE;
        }

        if (! $this->validated($user, $changes, $password)) {
            return self::FAILURE;
        }

        $this->line("Updating user #{$user->id} ({$user->email}):");

        foreach ($summary as $line) {
            $this->line("  - {$line}");
        }

        // Defaults to yes so --no-interaction still works in a deploy script.
        if (! $this->option('force') && ! $this->confirm('Apply these changes?', true)) {
            $this->warn('Aborted, nothing was changed.');

            return self::FAILURE;
        }

        // forceFill because email_verified_at is not mass assignable.
        $user->forceFill($changes)->save();

        if ($this->option('revoke-tokens')) {
            $revoked = $user->tokens()->delete();
            $this->line("Revoked {$revoked} API token(s).");
        }

        $this->info("Updated user #{$user->id} ({$user->email})".($user->isAdmin() ? ' — admin.' : '.'));

        if (isset($changes['password']) && ! $this->option('revoke-tokens')) {
            $this->comment('Existing API tokens still work. Re-run with --revoke-tokens to force a re-login.');
        }

        return self::SUCCESS;
    }

    /**
     * Find the account by primary key, or by email compared case-insensitively
     * because accounts created before emails were normalised may be mixed case.
     */
    private function resolveUser(string $identifier): ?User
    {
        if (ctype_digit($identifier)) {
            return User::find((int) $identifier);
        }

        return User::whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->first();
    }

    private function conflictingOptions(): ?string
    {
        $pairs = [
            ['admin', 'no-admin'],
            ['verified', 'unverified'],
        ];

        foreach ($pairs as [$on, $off]) {
            if ($this->option($on) && $this->option($off)) {
                return "--{$on} and --{$off} contradict each other; pass only one.";
            }
        }

        if ($this->option('password') && $this->option('prompt-password')) {
            return '--password and --prompt-password contradict each other; pass only one.';
        }

        return null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function collectChanges(User $user, ?string $password): array
    {
        $changes = [];
        $summary = [];

        if (($name = $this->option('name')) !== null) {
            $changes['name'] = $name;
            $summary[] = "name: {$user->name} -> {$name}";
        }

        if (($email = $this->option('email')) !== null) {
            $changes['email'] = Str::lower($email);
            $summary[] = "email: {$user->email} -> {$changes['email']}";
        }

        if ($password !== null && $password !== '') {
            $changes['password'] = Hash::make($password);
            $summary[] = 'password: replaced';
        }

        if ($this->option('admin') || $this->option('no-admin')) {
            $changes['is_admin'] = (bool) $this->option('admin');
            $summary[] = 'admin: '.($user->isAdmin() ? 'yes' : 'no').' -> '.($changes['is_admin'] ? 'yes' : 'no');
        }

        if ($this->option('verified') || $this->option('unverified')) {
            $changes['email_verified_at'] = $this->option('verified') ? now() : null;
            $summary[] = 'verified: '.($user->email_verified_at ? 'yes' : 'no').' -> '.($this->option('verified') ? 'yes' : 'no');
        }

        return [$changes, $summary];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function validated(User $user, array $changes, ?string $password): bool
    {
        $data = [];
        $rules = [];

        if (isset($changes['name'])) {
            $data['name'] = $changes['name'];
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        if (isset($changes['email'])) {
            $data['email'] = $changes['email'];
            $rules['email'] = ['required', 'email', 'max:255', function (string $attribute, mixed $value, callable $fail) use ($user) {
                $taken = User::whereRaw('LOWER(email) = ?', [Str::lower((string) $value)])
                    ->whereKeyNot($user->getKey())
                    ->exists();

                if ($taken) {
                    $fail('Another account already uses this email address.');
                }
            }];
        }

        if (isset($changes['password'])) {
            $data['password'] = $password;
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return false;
        }

        return true;
    }
}
