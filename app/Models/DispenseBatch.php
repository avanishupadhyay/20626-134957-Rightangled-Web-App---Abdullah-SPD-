<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DispenseBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'user_id',
        'pdf_path',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDispenses()
    {
        return $this->hasMany(OrderDispense::class, 'batch_id');
    }
}
