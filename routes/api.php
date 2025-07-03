<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\WebAppTimelineController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('prescriber/audit-logs/order',[WebAppTimelineController::class,'getPrescriberLogsByOrder']);
