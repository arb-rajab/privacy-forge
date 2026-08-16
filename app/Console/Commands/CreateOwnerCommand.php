<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

// R-05 (10-risk-register.md), the bootstrapping problem: a fresh instance
// needs at least one Owner account to log in as, but creating a user
// normally requires being logged in already (or shell access to
// application code, which real login exists specifically to remove).
// This command is the sanctioned way around that circularity — the same
// pattern RegisterReferenceConnectorCommand already established for the
// analogous connector-bootstrap problem: an artisan command a self-hoster
// runs once, not direct DB manipulation.
class CreateOwnerCommand extends Command
{
    protected $signature = 'privacy-forge:create-owner
        {--name= : Full name of the Owner account}
        {--email= : Login email}
        {--password= : Password (prompted securely if omitted)}';

    protected $description = 'Create the first Owner account on a fresh instance, without requiring an existing session or shell access to application code';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (input hidden)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $owner = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'owner',
            'active' => true,
        ]);

        $this->info("Owner account created: {$owner->email}");
        $this->line('Log in at /login with the password you just entered.');

        return self::SUCCESS;
    }
}
