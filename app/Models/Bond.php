<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Bond extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'bond';

    protected $fillable = ['code', 'name', 'first_purchse','is_global', 'minimun_amount', 'value_bond'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function purchaseOrderHeaderApply()
    {
        return $this->belongsToMany(PurchaseOrderHeader::class, "applied_bonuses","bond_id","purchase_order_header_id","id")->withPivot("id","bond_id","purchase_order_header_id","email");
    }
}
