<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list
                            {--search= : Only show users whose name or email contains this}
                            {--admin : Only show admins}
                            {--limit=50 : How many rows to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the accounts in this environment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = User::query()->orderBy('id');

        if ($this->option('admin')) {
            $query->admins();
        }

        if ($search = $this->option('search')) {
            $term = '%'.Str::lower($search).'%';

            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
            });
        }

        $total = $query->count();
        $limit = max(1, (int) $this->option('limit'));
        $users = $query->limit($limit)->get();

        if ($users->isEmpty()) {
            $this->warn('No matching users.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Admin', 'Verified', 'Created'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->isAdmin() ? 'yes' : '',
                $user->email_verified_at ? 'yes' : 'no',
                $user->created_at?->toDateString(),
            ])->all()
        );

        $this->line($users->count() === $total
            ? "{$total} user(s)."
            : "Showing {$users->count()} of {$total}. Raise --limit to see more.");

        return self::SUCCESS;
    }
}
