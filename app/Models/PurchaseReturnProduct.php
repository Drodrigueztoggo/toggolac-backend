<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnProduct extends Model
{
    use HasFactory;

    protected $table = 'purchase_return_products';

    protected $fillable = [
        'purchase_order_detail_id',
        'amount',
        'return_reason',
        'return_images',
        'return_status',
        'comment_shopper'
    ];

    protected $appends = ['images'];

    public function getImagesAttribute()
    {
        $imageUrls = json_decode($this->return_images);
        if ($imageUrls !== null) {
            // Reemplaza las barras inclinadas con barras inversas para obtener URLs válidas en PHP
            $imageUrls = array_map(function ($url) {
                return url('storage/' . $url);
            }, $imageUrls);
        
            // Puedes usar $imageUrls como un array de rutas de imágenes válidas en PHP
              return $imageUrls;

        } else {
            // Manejo de errores si el JSON no se pudo decodificar
            return null;
        }
    }

    public function detailProduct()
    {
        return $this->belongsTo(PurchaseOrderDetail::class, 'purchase_order_detail_id');
    }


    
}
