<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'mode', 'approval_threshold', 'allows_student_representative', 'is_active'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function occurrenceCategories(): HasMany
    {
        return $this->hasMany(OccurrenceCategory::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    protected function casts(): array
    {
        return [
            'approval_threshold' => 'decimal:2',
            'allows_student_representative' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
