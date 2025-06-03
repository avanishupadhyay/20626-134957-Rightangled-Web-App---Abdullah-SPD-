<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders'; // Table name
    protected $fillable = ['customer_id','order_number','customer_phone','customer_name','customer_email','discount_codes','currency','total_price'];
    
}
