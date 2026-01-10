<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RepairUserPasswords extends Command
{
    /**
     * Repair accounts that are missing passwords by generating a temporary one.
     *
     * @var string
     */
    protected $signature = 'user:repair-passwords {--email=} {--admin-only : Limit repair to the default admin user}';

    /**
     * @var string
     */
    protected $description = 'Repair accounts with missing passwords by setting a temporary password.';

    public function handle(): int
    {
        $query = User::query()
            ->where(fn ($q) => $q->whereNull('password')->orWhere('password', ''));

        if ($this->option('admin-only')) {
            $query->where('email', $this->option('email') ?? 'admin@ghanem-lvju-egypt.com');
        } elseif ($this->option('email')) {
            $query->where('email', $this->option('email'));
        }

        $updated = 0;

        $query->chunkById(50, function ($users) use (&$updated) {
            foreach ($users as $user) {
                $temporaryPassword = Str::random(16);

                $user->forceFill([
                    'password' => $temporaryPassword,
                    'password_changed_at' => now(),
                ])->save();

                $updated++;

                $this->line(sprintf(
                    'Temporary password set for user #%s (%s). Prompt the user to reset it. Temporary password: %s',
                    $user->id,
                    $user->email ?? 'N/A',
                    $temporaryPassword
                ));
            }
        });

        if ($updated === 0) {
            $this->info('No users with missing passwords were found.');

            return self::SUCCESS;
        }

        $this->warn('Temporary passwords were generated. Ensure affected users reset their passwords immediately.');

        return self::SUCCESS;
    }
}
