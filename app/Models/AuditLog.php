<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'order_id', 'action', 'details','checker_prescription_file'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
