<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

class CreateUserProfile
{
    /**
     * Auto-provision an empty profile row right after registration — every
     * field is nullable, so this is just a 1:1 placeholder the user fills
     * in later. Not wired into CreateNewUser itself; this listener is the
     * single place that owns "what happens after a user registers".
     */
    public function handle(Registered $event): void
    {
        $event->user->profile()->create([]);
    }
}
