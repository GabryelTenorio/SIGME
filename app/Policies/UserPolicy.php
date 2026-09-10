<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        if (! $user->hasActiveAccess()) {
            return false;
        }

        return $user->is_platform_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return (bool) $user->organization_id && (
            $user->hasPermission('usuarios.gerenciar')
            || $user->accessibleSchools()->contains(
                fn (School $school) => $user->hasPermission('usuarios.gerenciar', $school),
            )
        );
    }

    public function view(User $user, User $managedUser): bool
    {
        if ($managedUser->is_platform_admin || $managedUser->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->hasPermission('usuarios.gerenciar')) {
            return true;
        }

        $manageableIds = $user->schoolsWithPermission('usuarios.gerenciar')->pluck('id');
        $assignments = $managedUser->roleAssignments;

        if ($assignments->contains(fn ($assignment): bool => $assignment->school_id === null)) {
            return false;
        }

        $targetSchoolIds = $managedUser->schools->pluck('id')
            ->merge($assignments->pluck('school_id'))->unique();

        return $targetSchoolIds->isNotEmpty() && $targetSchoolIds->diff($manageableIds)->isEmpty();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $managedUser): bool
    {
        return $this->view($user, $managedUser);
    }

    public function delete(User $user, User $managedUser): bool
    {
        return false;
    }
}
