<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirstAccessToken extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'token_hash', 'sent_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
