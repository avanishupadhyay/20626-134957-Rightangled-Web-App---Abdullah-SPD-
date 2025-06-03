<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    use HasFactory;

    protected $table = 'user_logs'; // Table name
    protected $fillable = ['customer_id', 'action', 'first_name', 'last_name', 'email','country_isd','phone','customer_created_at','response','marketing_agreement'];

    
}
