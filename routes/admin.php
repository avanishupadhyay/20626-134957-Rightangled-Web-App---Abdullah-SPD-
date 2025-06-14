<?php

use App\Http\Controllers\Admin\ConfigurationsController;
use App\Http\Controllers\Admin\UserLogsController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\LoyalityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PrescriberOrderController;
use App\Http\Controllers\Admin\CheckerOrderController;
use App\Http\Controllers\Admin\ReportController;




Route::post('/configurations/make-slug', [ConfigurationsController::class, 'make_slug'])->name('configurations.make_slug');
Route::post('/configurations/upload-files', [ConfigurationsController::class, 'upload_files'])->name('configurations.upload_files');
Route::post('/configurations/remove-file', [ConfigurationsController::class, 'remove_file'])->name('configurations.remove_files');

Route::middleware(['auth'])->prefix('admin')->group(function () {


	/*Route for configurations*/
	Route::match(['get'], '/configurations', [ConfigurationsController::class, 'admin_prefix'])->name('admin.configurations');
	Route::match(['get'], '/configurations/index', [ConfigurationsController::class, 'admin_index'])->name('admin.configurations.admin_index');
	Route::match(['get', 'post'], '/configurations/add', [ConfigurationsController::class, 'admin_add'])->name('admin.configurations.admin_add');
	Route::match(['get', 'post'], '/configurations/edit/{id}', [ConfigurationsController::class, 'admin_edit'])->name('admin.configurations.admin_edit');
	Route::match(['get'], '/configurations/delete/{id}', [ConfigurationsController::class, 'admin_delete'])->name('admin.configurations.admin_delete');
	Route::match(['get'], '/configurations/view/{id?}', [ConfigurationsController::class, 'admin_view'])->name('admin.configurations.admin_view');
	Route::match(['get', 'post'], '/configurations/prefix/{prefix?}', [ConfigurationsController::class, 'admin_prefix'])->name('admin.configurations.admin_prefix');
	Route::match(['post'], '/configurations/save_config/{prefix}', [ConfigurationsController::class, 'save_config'])->name('admin.configurations.save_config');
	Route::match(['get'], '/configurations/change/{id}', [ConfigurationsController::class, 'admin_change'])->name('admin.configurations.admin_change');
	Route::match(['get'], '/configurations/moveup/{id}', [ConfigurationsController::class, 'admin_moveup'])->name('admin.configurations.admin_moveup');
	Route::match(['get'], '/configurations/movedown/{id}', [ConfigurationsController::class, 'admin_movedown'])->name('admin.configurations.admin_movedown');


	/* User Logs  */

	Route::resource('roles', RoleController::class);
	Route::resource('users', UserController::class);

	/* Store */
	Route::name('admin.')->group(function () {
		Route::match(['get', 'post'], '/stores/set_store', [StoreController::class, 'set_store'])->name('stores.set_store');
		Route::resource('stores', StoreController::class);

		Route::get('/report', [ReportController::class, 'index'])->name('report');
		Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
		
	});

	//order listing
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{id}/view', [OrderController::class, 'view'])->name('orders.view');
Route::get('/orders/{order}/download-pdf', [OrderController::class, 'downloadPDF'])->name('orders.downloadPDF');
// Route::get('/shopify/add-order-metafields/{orderId}', [OrderController::class, 'addMetafields']);

//prescriber route
Route::post('/orders/{orderId}/prescribe', [OrderController::class, 'overrideaction'])->name('orders.prescribe');
Route::get('Prescriber/orders', [PrescriberOrderController::class, 'index'])->name('prescriber_orders.index');
Route::get('Prescriber/orders/{id}/view', [PrescriberOrderController::class, 'view'])->name('prescriber_orders.view');
Route::post('Prescriber/orders/{orderId}/prescribe', [PrescriberOrderController::class, 'prescribe'])->name('orders.prescriber');


//Checker route
Route::get('Checker/orders', [CheckerOrderController::class, 'index'])->name('checker_orders.index');
Route::get('Checker/orders/{id}/view', [CheckerOrderController::class, 'view'])->name('checker_orders.view');
Route::post('Checker/orders/{orderId}/check', [CheckerOrderController::class, 'check'])->name('orders.checker');

});
