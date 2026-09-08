<?php

namespace App\Policies;

use App\Models\OccurrenceCategory;
use App\Models\User;

class OccurrenceCategoryPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->accessibleSchools()->contains(
            fn ($school) => $user->hasPermission('categorias.visualizar', $school)
                || $user->hasPermission('categorias.gerenciar_disponibilidade', $school),
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        return $user->organization_id === $occurrenceCategory->organization_id
            && $user->accessibleSchools()->where('organization_id', $occurrenceCategory->organization_id)->contains(
                fn ($school) => $user->hasPermission('categorias.visualizar', $school)
                    || $user->hasPermission('categorias.gerenciar_disponibilidade', $school),
            );
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
    public function update(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        return $this->canManageDefinition($user, $occurrenceCategory, 'categorias.editar');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        return false;
    }

    public function deactivate(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        return $this->canManageDefinition($user, $occurrenceCategory, 'categorias.desativar');
    }

    public function manageAvailability(User $user, OccurrenceCategory $occurrenceCategory): bool
    {
        if ($user->organization_id !== $occurrenceCategory->organization_id) {
            return false;
        }

        return $user->accessibleSchools()->where('organization_id', $occurrenceCategory->organization_id)->contains(
            fn ($school) => $user->hasPermission('categorias.gerenciar_disponibilidade', $school),
        );
    }

    private function canManageDefinition(User $user, OccurrenceCategory $category, string $permission): bool
    {
        if ($user->organization_id !== $category->organization_id) {
            return false;
        }

        if ($category->organization->mode === 'network') {
            return $user->hasPermission($permission);
        }

        return $category->organization->schools->contains(
            fn ($school) => $user->canAccessSchool($school) && $user->hasPermission($permission, $school),
        );
    }
}
