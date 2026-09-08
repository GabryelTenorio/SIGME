<?php

namespace App\Policies;

use App\Models\Environment;
use App\Models\User;

class EnvironmentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->accessibleSchools()->contains(
            fn ($school) => $user->hasPermission('ambientes.visualizar', $school)
                || $user->hasPermission('ambientes.gerenciar', $school),
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Environment $environment): bool
    {
        return $user->canAccessSchool($environment->school)
            && ($user->hasPermission('ambientes.visualizar', $environment->school)
                || $user->hasPermission('ambientes.gerenciar', $environment->school));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Environment $environment): bool
    {
        return $user->canAccessSchool($environment->school)
            && ($user->hasPermission('ambientes.editar', $environment->school)
                || $user->hasPermission('ambientes.gerenciar', $environment->school));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Environment $environment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Environment $environment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Environment $environment): bool
    {
        return false;
    }

    public function deactivate(User $user, Environment $environment): bool
    {
        return $user->canAccessSchool($environment->school)
            && ($user->hasPermission('ambientes.desativar', $environment->school)
                || $user->hasPermission('ambientes.gerenciar', $environment->school));
    }
}
