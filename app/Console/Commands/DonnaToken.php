<?php

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('donna:token {name=terminal : A label for where the token will be used}')]
#[Description('Create an API token for Josh. Run on the server; the token is shown once.')]
class DonnaToken extends Command
{
    public function handle(): int
    {
        $user = Person::josh()->user;

        $token = $user->createToken($this->argument('name'))->plainTextToken;

        $this->components->info("Token for {$user->name} ({$this->argument('name')}). It will not be shown again:");
        $this->line($token);
        $this->newLine();
        $this->line('Put it in the .env on your Mac as DONNA_API_TOKEN.');

        return self::SUCCESS;
    }
}
