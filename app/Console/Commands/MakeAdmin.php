<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'hort:make-admin {email}';

    protected $description = 'Grant admin (user-management) rights to the user with the given email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::firstWhere('email', $email);

        if (! $user) {
            $this->error("Kein Benutzer mit E-Mail {$email} gefunden.");

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info("{$user->name} ist jetzt Administrator:in.");

        return self::SUCCESS;
    }
}
