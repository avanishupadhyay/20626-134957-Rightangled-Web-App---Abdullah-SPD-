<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Order; 

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
        //$shopDomain = 'rightangled-store.myshopify.com'; // e.g., yourstore.myshopify.com
        $shopDomain = config('Shopify.api_host'); // e.g., yourstore.myshopify.com
        //$accessToken = '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';
        $accessToken = config('Shopify.access_token');
        $apiVersion = '2024-10';
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get("https://{$shopDomain}/admin/api/{$apiVersion}/orders.json", [
            'status' => 'any', // open, closed, cancelled, or any
            'limit' => 50,
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
                    ]
                );
            }
            $this->info("Synced " . count($orders) . " orders from Shopify.");
        } else {
            $this->error("Failed to fetch orders: " . $response->body());
        }
    }
}
