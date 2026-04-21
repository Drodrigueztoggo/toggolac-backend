<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ApplyBond extends Pivot
{
    use HasFactory;

    protected $table = 'applied_bonuses';

    protected $fillable = ['bond_id', 'purchase_order_header_id'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
