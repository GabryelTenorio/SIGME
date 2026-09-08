<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'year', 'next_number'])]
class OccurrenceSequence extends Model
{
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
