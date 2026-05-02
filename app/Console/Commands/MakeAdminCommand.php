<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    protected $signature = 'admin:make {email : Email address of the user to promote}';

    protected $description = 'Grant admin privileges to a user';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::usesEncryptedStorage()
            ? User::findByEmail($email)
            : User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("User [{$user->name}] is now an administrator.");

        return self::SUCCESS;
    }
}
