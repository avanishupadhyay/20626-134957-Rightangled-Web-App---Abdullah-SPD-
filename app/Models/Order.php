<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders'; // Table name
    protected $fillable = ['customer_id', 'order_number', 'name', 'email', 'total_price', 'financial_status', 'fulfillment_status', 'order_data', 'store_id', 'trackingNumber','error'];


    public function orderaction()
    {
        return $this->hasOne(OrderAction::class, 'order_id', 'order_number');
    }
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }
    public function latestOrderAction()
    {
        return $this->hasOne(OrderAction::class, 'order_id', 'order_number')->latest('decision_timestamp');
    }
    public function dispense()
    {
        return $this->hasOne(OrderDispense::class, 'order_id');
    }
}
