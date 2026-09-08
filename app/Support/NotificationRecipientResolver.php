<?php

namespace App\Support;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    /**
     * @param  array<int, string>|string  $permissions
     * @return Collection<int, User>
     */
    public function forSchoolAndPermissions(School $school, array|string $permissions): Collection
    {
        $permissionSlugs = collect((array) $permissions)
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->unique()
            ->values()
            ->all();

        if ($permissionSlugs === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function ($users) use ($school, $permissionSlugs): void {
                $users->where('is_platform_admin', true)
                    ->orWhere(function ($organizationUsers) use ($school, $permissionSlugs): void {
                        $organizationUsers
                            ->where('organization_id', $school->organization_id)
                            ->whereHas('roleAssignments', function ($assignments) use ($school, $permissionSlugs): void {
                                $assignments
                                    ->where(function ($scope) use ($school): void {
                                        $scope->whereNull('school_id')->orWhere('school_id', $school->id);
                                    })
                                    ->whereHas('role.permissions', function ($permissionQuery) use ($permissionSlugs): void {
                                        $permissionQuery->whereIn('slug', $permissionSlugs);
                                    });
                            });
                    });
            })
            ->orderBy('id')
            ->get();
    }
}
