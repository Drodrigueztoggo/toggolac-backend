<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxCategorie extends Model
{
    use HasFactory;

    protected $table = 'taxes_categories';

    protected $fillable = [
        'category_ids', 'tax_id', 'category_id'
    ];


    public function infoTax()
    {
        return $this->belongsTo(Tax::class, 'tax_id')->select(
            'id',
            'code',
            'is_manual',
            'product_dependency',
            'tax_dependency_id',
            'destination_country_id',
            'name',
            'type',
            'value',
            'if_price',
            'value_conditional',
            'user_id'
        );
    }

}
