<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAction extends Model
{

    protected $table = 'order_actions';

    protected $fillable = ['order_id', 'user_id', 'clinical_reasoning', 'decision_status', 'rejection_reason', 'on_hold_reason', 'decision_timestamp', 'prescribed_pdf','release_hold_reason'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    protected $casts = [
        'decision_timestamp' => 'datetime', // This line is key!
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_number');
    }
}
