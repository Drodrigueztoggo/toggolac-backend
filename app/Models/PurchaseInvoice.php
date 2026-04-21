<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{

    use HasFactory, SoftDeletes;

    protected $table = 'purchase_invoices';

    protected $fillable = [
        'purchase_id',
        'file',
        'extension',
        'user_id'
    ];


    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->file) ? url('storage/' . $this->file) : null;
    }
}
