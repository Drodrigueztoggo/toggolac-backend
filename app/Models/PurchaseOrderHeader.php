<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderHeader extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_order_headers';

    protected $fillable = [
        'order_token',
        'client_id',
        'mall_id',
        'store_id',
        'shipment_status',
        'shipment_price',
        'purchase_status',
        'purchase_status_id',
        'personal_shopper_id',
        'destination_address',
        'destination_country_id',
        'destination_state_id',
        'origin_city_id',
        'destination_city_id',
        'conveyor_id',
        'start_date',
        'estimated_date',
        'guide_number',
        'carrier',
        'invoice_number',
        'final_sale_acknowledged',
        'final_sale_acknowledged_at',
    ];

    protected $dates = ['deleted_at'];

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate invoice number after the record is created and has an ID
        static::created(function (self $order) {
            if (empty($order->invoice_number)) {
                $order->updateQuietly([
                    'invoice_number' => 'TGG-' . date('Y') . '-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }

    /** Full status change timeline, ordered chronologically */
    public function statusHistory()
    {
        return $this->hasMany(PurchaseOrderHeaderLog::class, 'purchase_order_id')
                    ->with('status:id,name', 'previousStatus:id,name')
                    ->orderBy('created_at', 'asc');
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'purchase_id')->select('id', 'purchase_id', 'file');
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class, 'id', 'purchase_order_id')->select("id",
        "user_id",
        "purchase_order_id",
        "general_rating",
        "delivery_time",
        "product_quality",
        "customer_service",
        "store_navigation",
        "payment_process",
        "review");
    }


    public function receptionCenter()
    {
        return $this->hasMany(ReceptionCenter::class, 'purchase_id')->select('id', 'purchase_product_id', 'status', 'purchase_id', 'optimal_conditions_product', 'verified_quantity', 'conditions_brand', 'invoice_order');
    }

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'purchase_order_header_id');
    }

    public function destinationCountry()
    {
        return $this->belongsTo(Country::class, 'destination_country_id')->select('id', 'name');
    }
    public function destinationCity()
    {
        return $this->belongsTo(City::class, 'destination_city_id')->select('id', 'name');
    }
    public function purchaseStatus()
    {
        return $this->belongsTo(PurchaseOrderStatus::class, 'purchase_status_id')->select('id', 'name');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'id', 'purchase_order_id');
    }

    public function store()
    {
        return $this->belongsTo(StoreMall::class, 'store_id');
    }
    public function personalShopper()
    {
        return $this->belongsTo(User::class, 'personal_shopper_id');
    }
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id')->select('id', 'name', 'last_name', 'personal_id', 'email');
    }
    public function mallInfo()
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }
    public function infoTransaction()
    {
        return $this->belongsTo(Transaction::class, 'id', 'purchase_order_id');
    }

    public function bondPurchaseOrder()
    {
        return $this->belongsToMany(Bond::class, "applied_bonuses","purchase_order_header_id","bond_id","id")->withPivot("id","bond_id","purchase_order_header_id","email");
    }
}
