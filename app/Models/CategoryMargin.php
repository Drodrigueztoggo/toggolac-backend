<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMargin extends Model
{
    protected $fillable = [
        'category_id',
        'min_margin_percent',
        'price_increase_alert_threshold',
        'price_increase_unpublish_threshold',
    ];

    protected $casts = [
        'min_margin_percent'                => 'decimal:2',
        'price_increase_alert_threshold'    => 'decimal:2',
        'price_increase_unpublish_threshold'=> 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public static function forCategory(int $categoryId): self
    {
        return static::firstOrNew(
            ['category_id' => $categoryId],
            [
                'min_margin_percent'                => 30.00,
                'price_increase_alert_threshold'    => 10.00,
                'price_increase_unpublish_threshold'=> 30.00,
            ]
        );
    }

    public function minSellingPrice(float $supplierPrice): float
    {
        return round($supplierPrice * (1 + $this->min_margin_percent / 100), 2);
    }
}
