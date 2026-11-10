<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('app:create-admin {email? : The admin email address} {--name= : The display name}')]
#[Description('Create an admin user (the password is always prompted, never passed as an argument)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email address');
        $name = $this->option('name') ?? $this->ask('Name', 'Admin');
        $password = $this->secret('Password (at least 12 characters)');

        if ($password !== $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User($validator->validated());
        $user->is_admin = true;
        $user->save();

        $this->info("Admin {$user->email} created.");

        return self::SUCCESS;
    }
}
