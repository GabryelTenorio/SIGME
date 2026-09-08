<?php

namespace App\Models;

use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'parent_id', 'code', 'name', 'type', 'building', 'floor', 'capacity', 'description', 'is_active'])]
class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
    use HasFactory;

    public const TYPES = [
        'classroom' => 'Sala de aula',
        'laboratory' => 'Laboratório',
        'library' => 'Biblioteca',
        'office' => 'Administrativo / Secretaria',
        'direction' => 'Direção',
        'kitchen' => 'Cozinha / Refeitório',
        'bathroom' => 'Banheiro',
        'court' => 'Quadra',
        'patio' => 'Pátio',
        'corridor' => 'Corredor',
        'outdoor' => 'Área externa',
        'technical_room' => 'Sala técnica',
        'other' => 'Outro',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EnvironmentHistory::class)->latest('created_at');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class);
    }

    public function scopeAvailableForNewOccurrences(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Outro';
    }

    public function locationLabel(): string
    {
        return collect([$this->building, $this->floor, $this->parent?->name])->filter()->join(' · ') ?: '—';
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity' => 'integer'];
    }
}
