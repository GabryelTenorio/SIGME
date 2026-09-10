<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['organization_id', 'school_id', 'occurrence_id', 'created_by', 'assigned_user_id', 'code', 'code_year', 'code_sequence', 'title', 'description', 'priority_snapshot', 'status', 'due_date', 'planned_at', 'estimated_cost', 'requires_purchase', 'external_service', 'external_provider_name', 'external_service_description', 'external_provider_contact', 'external_provider_tax_id', 'asset_replacement', 'asset_disposal', 'extraordinary_purchase', 'approval_required', 'notes', 'diagnosis', 'solution', 'started_at', 'completed_at', 'emergency_authorized_by', 'emergency_authorized_at', 'emergency_reason', 'emergency_ratification_due_at', 'emergency_ratified_by', 'emergency_ratified_at'])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    public const STATUSES = ['AGUARDANDO_APROVACAO', 'APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_MATERIAL', 'PAUSADA', 'CONCLUIDA', 'REJEITADA', 'CANCELADA'];

    public const STATUS_LABELS = ['AGUARDANDO_APROVACAO' => 'Aguardando aprovação', 'APROVADA' => 'Aprovada', 'EM_EXECUCAO' => 'Em execução', 'AGUARDANDO_MATERIAL' => 'Aguardando material', 'PAUSADA' => 'Pausada', 'CONCLUIDA' => 'Concluída', 'REJEITADA' => 'Rejeitada', 'CANCELADA' => 'Cancelada'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function emergencyAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emergency_authorized_by');
    }

    public function emergencyRatifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emergency_ratified_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_order_members')->withTimestamps();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ServiceOrderHistory::class)->latest('created_at');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ServiceOrderMaterial::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(ServiceOrderWorkLog::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ServiceOrderCost::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(PrivateAttachment::class, 'attachable');
    }

    public function materialTotal(): string
    {
        if ($this->relationLoaded('materials')) {
            $total = BigDecimal::zero();
            foreach ($this->materials as $material) {
                $total = $total->plus($material->total_cost);
            }

            return (string) $total->toScale(2);
        }

        return (string) BigDecimal::of((string) $this->materials()->sum('total_cost'))->toScale(2);
    }

    public function additionalCostTotal(): string
    {
        if ($this->relationLoaded('costs')) {
            $total = BigDecimal::zero();
            foreach ($this->costs as $cost) {
                $total = $total->plus($cost->amount);
            }

            return (string) $total->toScale(2);
        }

        return (string) BigDecimal::of((string) $this->costs()->sum('amount'))->toScale(2);
    }

    public function totalCost(): string
    {
        return (string) BigDecimal::of((string) $this->materialTotal())
            ->plus($this->additionalCostTotal())
            ->toScale(2);
    }

    public function hasPendingEmergencyRatification(): bool
    {
        return $this->emergency_authorized_at !== null && $this->emergency_ratified_at === null;
    }

    public function emergencyRatificationIsOverdue(?CarbonInterface $at = null): bool
    {
        if (! $this->hasPendingEmergencyRatification() || $this->emergency_ratification_due_at === null) {
            return false;
        }

        return CarbonImmutable::instance($at ?? now())->isAfter($this->emergency_ratification_due_at);
    }

    protected function casts(): array
    {
        return ['due_date' => 'date', 'planned_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'emergency_authorized_at' => 'datetime', 'emergency_ratification_due_at' => 'datetime', 'emergency_ratified_at' => 'datetime', 'estimated_cost' => 'decimal:2', 'requires_purchase' => 'boolean', 'external_service' => 'boolean', 'asset_replacement' => 'boolean', 'asset_disposal' => 'boolean', 'extraordinary_purchase' => 'boolean', 'approval_required' => 'boolean'];
    }
}
