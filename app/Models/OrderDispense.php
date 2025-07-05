<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDispense extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'batch_id',
        'dispensed_at',
        'reprint_count',
    ];

    // Relationships
    public function batch()
    {
        return $this->belongsTo(DispenseBatch::class, 'batch_id');
    }
   public function order()
{
    return $this->belongsTo(Order::class, 'order_id', 'order_number');
}
}
