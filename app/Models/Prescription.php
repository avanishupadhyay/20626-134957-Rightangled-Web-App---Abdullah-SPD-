<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = ['order_id', 'prescriber_id', 'GPhC_GMC_number', 'signature_image', 'clinical_reasoning', 'decision_status', 'rejection_reason', 'on_hold_reason', 'decision_timestamp',];
    
    public function prescriber()
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }
}
