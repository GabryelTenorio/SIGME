<?php

namespace App\Observers;

use App\Models\User;
use App\Support\FirstAccessInvitationService;

class UserObserver
{
    public function __construct(private readonly FirstAccessInvitationService $invitations) {}

    public function created(User $user): void
    {
        if ($user->is_active && $user->requiresFirstAccess()) {
            $this->invitations->issue($user);
        }
    }

    public function updated(User $user): void
    {
        $needsNewInvitation = $user->wasChanged('email')
            || ($user->wasChanged('is_active') && $user->is_active);

        if ($needsNewInvitation && $user->is_active && $user->requiresFirstAccess()) {
            $this->invitations->issue($user);
        }
    }
}
