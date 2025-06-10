<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = ['order_id', 'prescriber_id','clinical_reasoning', 'decision_status', 'rejection_reason', 'on_hold_reason', 'decision_timestamp','prescribed_pdf'];
    
    public function prescriber()
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }
        protected $casts = [
        'decision_timestamp' => 'datetime', // This line is key!
    ];
}
