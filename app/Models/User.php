<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable(['organization_id', 'name', 'email', 'password', 'password_set_at', 'is_platform_admin', 'is_active', 'email_notifications_enabled', 'onboarding_completed_steps'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'email_notifications_enabled' => false,
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_assignments')
            ->withPivot('school_id')
            ->withTimestamps();
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function reportedOccurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'reporter_id');
    }

    public function internalNotifications(): HasMany
    {
        return $this->hasMany(InternalNotification::class);
    }

    public function firstAccessToken(): HasOne
    {
        return $this->hasOne(FirstAccessToken::class);
    }

    public function hasPermission(string $permission, ?School $school = null): bool
    {
        if (! $this->hasActiveAccess()) {
            return false;
        }

        if ($this->is_platform_admin) {
            return true;
        }

        if (! $this->is_active || ! $this->organization_id) {
            return false;
        }

        if ($school && $school->organization_id !== $this->organization_id) {
            return false;
        }

        return DB::table('role_assignments')
            ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_assignments.user_id', $this->id)
            ->where('roles.organization_id', $this->organization_id)
            ->where('permissions.slug', $permission)
            ->when(
                $school,
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('role_assignments.school_id')
                    ->orWhere('role_assignments.school_id', $school->id)),
                fn ($query) => $query->whereNull('role_assignments.school_id'),
            )
            ->exists();
    }

    public function canAccessSchool(School $school): bool
    {
        if (! $this->hasActiveAccess()) {
            return false;
        }

        if ($this->is_platform_admin) {
            return true;
        }

        if (! $this->is_active || $school->organization_id !== $this->organization_id) {
            return false;
        }

        return $this->schools()->whereKey($school->id)->exists()
            || $this->roleAssignments()->whereNull('school_id')->exists()
            || $this->roleAssignments()->where('school_id', $school->id)->exists();
    }

    public function isEligibleForServiceOrderAssignment(School $school): bool
    {
        return $this->is_active
            && $this->organization_id === $school->organization_id
            && $this->canAccessSchool($school)
            && $this->hasPermission('ordens_servico.visualizar', $school)
            && $this->hasPermission('ordens_servico.executar', $school);
    }

    /** @return Collection<int, School> */
    public function accessibleSchools(): Collection
    {
        if (! $this->hasActiveAccess()) {
            return collect();
        }

        if ($this->is_platform_admin) {
            return School::query()->where('is_active', true)->orderBy('name')->get();
        }

        if (! $this->organization_id) {
            return collect();
        }

        if ($this->roleAssignments()->whereNull('school_id')->exists()) {
            return School::query()
                ->where('organization_id', $this->organization_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $assignedSchoolIds = $this->roleAssignments()->whereNotNull('school_id')->pluck('school_id');

        return School::query()
            ->where('organization_id', $this->organization_id)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereIn('id', $this->schools()->select('schools.id'))
                ->orWhereIn('id', $assignedSchoolIds))
            ->orderBy('name')
            ->get();
    }

    public function hasActiveAccess(): bool
    {
        return $this->is_active
            && (! $this->organization_id || (bool) $this->organization?->is_active);
    }

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function requiresFirstAccess(): bool
    {
        return $this->password_set_at === null;
    }

    public function requiresTwoFactorAuthentication(): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->roles()
            ->whereIn('slug', config('security.two_factor.required_roles', []))
            ->exists();
    }

    /** @return Collection<int, School> */
    public function schoolsWithPermission(string $permission): Collection
    {
        return $this->accessibleSchools()->filter(
            fn (School $school): bool => $this->hasPermission($permission, $school),
        )->values();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'onboarding_completed_steps' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }
}
