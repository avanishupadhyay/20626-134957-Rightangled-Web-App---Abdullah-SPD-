<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loyality extends Model
{
    use HasFactory;

    protected $table = 'loyalities'; // Table name
    protected $fillable = ['coupon', 'customer_id', 'customer_email', 'customer_name'];

    
}
