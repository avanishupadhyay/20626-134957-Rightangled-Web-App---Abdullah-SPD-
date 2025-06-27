<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use Carbon\Carbon;
use App\Models\Store;


class SyncShopifyOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-shopify-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync orders from Shopify to Laravel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiVersion = '2024-10';

        $stores = Store::where('status', 1)->get();

        foreach ($stores as $store) {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $store->app_admin_access_token,
            ])->get("{$store->domain}/admin/api/{$apiVersion}/orders.json", [
                'status' => 'any',
                'limit' => 250,
            ]);

            if ($response->successful()) {
                $orders = $response->json()['orders'];
                foreach ($orders as $order) {
                    Order::updateOrCreate(
                        ['order_number' => $order['id']],
                        [
                            'order_number' => $order['id'],
                            'name' => $order['name'],
                            'email' => $order['email'],
                            'total_price' => $order['total_price'],
                            'financial_status' => $order['financial_status'],
                            'fulfillment_status' => $order['fulfillment_status'],
                            'order_data' => json_encode($order),
                            'store_id' => $store->id,
                            'created_at' => isset($order['created_at']) ? Carbon::parse($order['created_at']) : now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
