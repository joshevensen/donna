<?php

namespace App\Console\Commands;

use App\Auth\LoginCodes;
use App\Models\Person;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('donna:login')]
#[Description('Print a sign-in code and link for the web app. Run on the server; the way in when email is not working.')]
class DonnaLogin extends Command
{
    public function handle(LoginCodes $codes): int
    {
        $user = Person::josh()->user;

        $login = $codes->issue($user);

        $expires = $login->expiresAt->copy()->timezone(config('donna.timezone'))->format('g:i A');

        $this->components->info("Sign-in for {$user->email}. Works once, until {$expires}. Any earlier code has stopped working.");
        $this->line("Code: {$login->code}");
        $this->line("Link: {$login->url}");

        return self::SUCCESS;
    }
}
