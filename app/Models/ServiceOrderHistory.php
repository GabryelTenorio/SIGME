<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['service_order_id', 'actor_id', 'event_type', 'old_values', 'new_values', 'metadata'])]
class ServiceOrderHistory extends Model
{
    public $timestamps = false;

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Service order history is append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Service order history is append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'metadata' => 'array', 'created_at' => 'datetime'];
    }
}
