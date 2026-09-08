<?php

namespace App\Models;

use Database\Factories\OccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['organization_id', 'school_id', 'environment_id', 'occurrence_category_id', 'reporter_id', 'triaged_by', 'duplicate_of_id', 'protocol', 'protocol_year', 'protocol_sequence', 'title', 'description', 'impact', 'perceived_urgency', 'suggested_priority', 'confirmed_priority', 'status', 'triage_note', 'triaged_at', 'forwarded_destination'])]
class Occurrence extends Model
{
    /** @use HasFactory<OccurrenceFactory> */
    use HasFactory;

    public const STATUSES = ['ABERTA', 'EM_TRIAGEM', 'ENCAMINHADA', 'AGUARDANDO_INFORMACOES', 'NAO_PROCEDE', 'DUPLICADA', 'EM_ATENDIMENTO', 'RESOLVIDA', 'ENCERRADA'];

    public const IMPACTS = ['LOW' => 'Baixo', 'MEDIUM' => 'Médio', 'HIGH' => 'Alto'];

    public const URGENCIES = ['NORMAL' => 'Normal', 'SOON' => 'Breve', 'IMMEDIATE' => 'Imediata'];

    public const PRIORITIES = ['LOW' => 'Baixa', 'MEDIUM' => 'Média', 'HIGH' => 'Alta', 'URGENT' => 'Urgente'];

    public const STATUS_LABELS = ['ABERTA' => 'Aberta', 'EM_TRIAGEM' => 'Em triagem', 'ENCAMINHADA' => 'Encaminhada', 'AGUARDANDO_INFORMACOES' => 'Aguardando informações', 'NAO_PROCEDE' => 'Não procede', 'DUPLICADA' => 'Duplicada', 'EM_ATENDIMENTO' => 'Em atendimento', 'RESOLVIDA' => 'Resolvida', 'ENCERRADA' => 'Encerrada'];

    public static function suggestedPriority(string $impact, string $urgency): string
    {
        if ($urgency === 'IMMEDIATE') {
            return 'URGENT';
        }
        if ($urgency === 'SOON') {
            return $impact === 'LOW' ? 'MEDIUM' : 'HIGH';
        }

        return match ($impact) {
            'LOW' => 'LOW', 'MEDIUM' => 'MEDIUM', default => 'HIGH'
        };
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OccurrenceCategory::class, 'occurrence_category_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function triageResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OccurrenceHistory::class)->latest('created_at');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(PrivateAttachment::class, 'attachable');
    }

    public function priority(): string
    {
        return $this->confirmed_priority ?: $this->suggested_priority;
    }

    public function canBeResolvedFromServiceOrders(): bool
    {
        return $this->serviceOrders()->where('status', 'CONCLUIDA')->exists()
            && ! $this->serviceOrders()->whereNotIn('status', ['CONCLUIDA', 'CANCELADA', 'REJEITADA'])->exists();
    }

    public function concurrencyToken(): string
    {
        return hash('sha256', json_encode([
            'id' => $this->getKey(),
            'updated_at' => $this->getRawOriginal('updated_at'),
            'status' => $this->status,
            'confirmed_priority' => $this->confirmed_priority,
            'triage_note' => $this->triage_note,
            'triaged_by' => $this->triaged_by,
            'triaged_at' => $this->getRawOriginal('triaged_at'),
            'duplicate_of_id' => $this->duplicate_of_id,
            'forwarded_destination' => $this->forwarded_destination,
            'history_id' => $this->histories()->reorder()->max('id'),
        ], JSON_THROW_ON_ERROR));
    }

    protected function casts(): array
    {
        return ['triaged_at' => 'datetime'];
    }
}
