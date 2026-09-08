<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return (bool) $user->organization_id && (
            $user->hasPermission('escolas.gerenciar')
            || $user->accessibleSchools()->contains(
                fn (School $school) => $user->hasPermission('escolas.gerenciar', $school),
            )
        );
    }

    public function view(User $user, School $school): bool
    {
        return $user->canAccessSchool($school)
            && $user->hasPermission('escolas.gerenciar', $school);
    }

    public function create(User $user): bool
    {
        return (bool) $user->organization_id
            && $user->hasPermission('escolas.gerenciar');
    }

    public function update(User $user, School $school): bool
    {
        return $this->view($user, $school);
    }

    public function delete(User $user, School $school): bool
    {
        return false;
    }
}
