<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescriber extends Model
{
    protected $fillable = ['user_id', 'gphc_number', 'signature_image'];

    // Relationship with User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with OrderAction
    public function orderaction(): HasMany
    {
        return $this->hasMany(OrderAction::class);
    }
}
