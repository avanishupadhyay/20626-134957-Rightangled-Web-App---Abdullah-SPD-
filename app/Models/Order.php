<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders'; // Table name
    protected $fillable = ['customer_id','order_number','name','email','total_price','financial_status','fulfillment_status','order_data'];
    

    public function prescription()
{
    return $this->hasOne(Prescription::class, 'order_id', 'order_number');
}
}
