<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
	protected $fillable = [
		'name',
		'description',
		'image',
		'domain',
		'status',
		'app_client_id',
		'app_secret_key',
		'app_admin_access_token'

	];

	public function getStoreIdByStoreUrl($store_url)
	{
		$store = Store::where('domain', $store_url)->first();
		return !empty($store->id) ? $store->id : 0;
	}
}
