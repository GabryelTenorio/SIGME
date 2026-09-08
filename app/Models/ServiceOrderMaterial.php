<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['service_order_id', 'created_by', 'description', 'quantity', 'unit', 'unit_cost', 'total_cost'])]
class ServiceOrderMaterial extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }
}
