<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ShopifyInstallerController;
use App\Http\Middleware\CustomCors;
use App\Http\Controllers\Admin\DashboardController; 
use Milon\Barcode\Facades\DNS1D;


require __DIR__.'/auth.php';
require __DIR__.'/admin.php';


Route::get('/', function () {
	return redirect()->route('admin.dashboard');
});


Route::get('clear-all', function() {
	\Artisan::call('config:clear');
	\Artisan::call('route:clear');
	\Artisan::call('view:clear');
	\Artisan::call('cache:clear');
	\Artisan::call('optimize:clear');
	\Artisan::call('config:cache');
    \Artisan::call('route:cache');
    echo 'success';
});

Route::get('route-cache', function() {
	\Artisan::call('route:cache');
	echo 'success';
});

Route::get('route-clear', function() {
	\Artisan::call('route:clear');
	echo 'success';
});

Route::get('config-cache', function() {
	\Artisan::call('config:cache');
	echo 'success';
});

Route::get('config-clear', function() {
	\Artisan::call('config:clear');
	echo 'success';
});

Route::get('sync-shopify-orders', function() {
	\Artisan::call('sync-shopify-orders');
	echo 'success';
});



Route::prefix('admin')->name('admin.')->group(function () {
    // Route::get('/dashboard', function () {
    //     return view('admin.dashboard');
    // })->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');


	

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});



Route::get('/shopify/products', [ShopifyController::class, 'index']);
Route::match(['get','post'], '/shopify/loyalty', [ShopifyController::class, 'showLoyalty']);

Route::get('/shopify/install', [ShopifyInstallerController::class, 'redirectToShopify']);
Route::get('/shopify/callback', [ShopifyInstallerController::class, 'handleShopifyCallback']);


//Route::get('/shopify/draftOrderCreate', [ShopifyController::class, 'draftOrderCreate']);
//Route::get('/shopify/getDraftOrderIdByOrderId', [ShopifyController::class, 'getDraftOrderIdByOrderId']);
Route::match(['get','post'], '/shopify/testrecord', [ShopifyController::class, 'testRecord']);
Route::match(['get','post'], '/shopify/draftOrderCreateGraphQL', [ShopifyController::class, 'draftOrderCreateGraphQL']);
Route::match(['get','post'], '/shopify/draftOrderUpdateGraphQL', [ShopifyController::class, 'draftOrderUpdateGraphQL']);
//Route::match(['get','post'], '/shopify/getUserDiscounts', [ShopifyController::class, 'getUserDiscounts']);
Route::match(['get','post'], '/shopify/loyaltyTransaction/{payload?}', [ShopifyController::class, 'loyaltyTransaction']);


//Route::post('/webhook/shopify', [ShopifyController::class, 'handle']);
Route::match(['get','post'], '/webhook/shopify', [ShopifyController::class, 'handle']);
Route::match(['get','post'], '/webhook/shopify2', [ShopifyController::class, 'handleTest']);


Route::get('/barcode/{orderId}', function ($orderId) {
    $barcode = new \Milon\Barcode\DNS1D();
    $pngData = $barcode->getBarcodePNG($orderId, 'C128', 2, 60);
    return response(base64_decode($pngData))->header('Content-Type', 'image/png');
});
;


Route::get('/phpinfo',function(){
	return phpinfo();
});