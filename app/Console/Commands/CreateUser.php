<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create
                            {--name= : Display name}
                            {--email= : Email address, stored lowercased}
                            {--password= : Password; you are prompted for it if omitted}
                            {--admin : Give the account dashboard access}
                            {--unverified : Leave the email unverified}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user account from the command line';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = Str::lower((string) ($this->option('email') ?: $this->ask('Email')));

        // Passing --password puts it in the shell history; prompting doesn't.
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                // Matches RegisterRequest: compared case-insensitively, because
                // accounts created before emails were normalised may be stored
                // with mixed case.
                'email' => ['required', 'email', 'max:255', function (string $attribute, mixed $value, callable $fail) {
                    if (User::whereRaw('LOWER(email) = ?', [Str::lower((string) $value)])->exists()) {
                        $fail('An account with this email address already exists.');
                    }
                }],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'terms_accepted_at' => now(),
            'is_admin' => (bool) $this->option('admin'),
        ]);

        // Verified by default: there is nobody to click the link, and the
        // dashboard sits behind the 'verified' middleware (routes/web.php).
        // Set after create because email_verified_at is not mass assignable.
        if (! $this->option('unverified')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->info("Created user #{$user->id} ({$user->email})".($user->isAdmin() ? ' as an admin.' : '.'));

        return self::SUCCESS;
    }
}
