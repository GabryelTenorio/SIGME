<?php

namespace App\Models;

use Database\Factories\OccurrenceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'identifier', 'description', 'is_active', 'display_order', 'is_fallback'])]
class OccurrenceCategory extends Model
{
    /** @use HasFactory<OccurrenceCategoryFactory> */
    use HasFactory;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'category_school')->withTimestamps();
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class);
    }

    public function scopeAvailableForSchool(Builder $query, School $school): Builder
    {
        return $query->where('organization_id', $school->organization_id)
            ->where('is_active', true)
            ->whereHas('schools', fn (Builder $schools) => $schools->whereKey($school->id));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_fallback' => 'boolean', 'display_order' => 'integer'];
    }
}
