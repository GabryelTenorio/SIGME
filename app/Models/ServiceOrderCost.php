<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_order_id', 'created_by', 'type', 'description', 'amount'])]
class ServiceOrderCost extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
