<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\UserLog;
use App\Models\Order;
use App\Models\OrderAction;
use App\Models\Loyality;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ShopifyController extends Controller
{
    protected $shopifyService;
    protected $storeDomain;
    protected $accessToken;
    //protected $apiKey;
    //protected $apiSecret;
    protected $siteEnvironment;
    protected $coniqLoyaltyLogo;
    protected $coniqLoyaltyTitle;
    protected $apiGraphQLUrl;

    protected $amount_key;

    //Shopify app credentials
    protected $clientId;
    protected $clientSecret;

    public function __construct(ShopifyService $shopifyService)
    {

        $this->shopifyService = $shopifyService;

        $this->client = new Client();

        $this->siteEnvironment   = config('Site.environment');

        $this->accessToken       = config('Shopify.access_token');
        $this->storeDomain       = config('Shopify.api_host');

        $this->clientId     = config('Shopify.app_client_id');
        $this->clientSecret = config('Shopify.app_client_secret');

        $this->coniqLoyaltyTitle = config('Coniq.loyalty_program_title');
        $this->coniqLoyaltyLogo  = asset('storage/configuration-images/' . config('Coniq.loyalty_logo'));
        $this->apiGraphQLUrl     = 'https://' . $this->storeDomain . '/admin/api/2024-10/graphql.json';

        $this->amount_key = 'total_line_items_price'; /* Full amount of order */
        //$this->amount_key = 'total_price'; /* Final price after discount */
    }

    public function index()
    {
        $products = $this->shopifyService->getProducts();
        dd($products);
        return response()->json($products);
    }

    public function handle(Request $request)
    {
        // Verify the webhook signature (optional but recommended for security)
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $data = $request->getContent();

        $event   = $request->header('X-Shopify-Topic');
        $payload = $request->all();

        switch ($event) {
            case 'orders/create':
                $this->handleOrderCreate($request);
                break;

            case 'orders/updated':
                $this->handleOrderUpdate($request);
                break;

            case 'orders/delete':
                $this->handleOrderDelete($payload);
                break;

            case 'orders/cancelled': // Shopify uses "orders/cancelled"
                $this->handleOrderCancel($payload);
                break;

            case 'fulfillment_holds/released': 
                $this->handleOrderRelease($payload);
                break;

            default:
                Log::warning("Unhandled Shopify event: $event");
        }
        
        return response('Webhook handled', 200);
    }


    protected function handleOrderCreate(Request $request)
    {
        $orderData = $request->all();
        $shopDomain = $request->header('X-Shopify-Shop-Domain');

        $normalizedShopUrl = 'https://' . $shopDomain;
        // Now match store using the full URL
        $store = \App\Models\Store::where('domain', $normalizedShopUrl)->first();
        if(!$store){
            return;
        }
        Log::info("Matched Store:", [$store]);
        \App\Models\Order::updateOrCreate(
            ['order_number' => $orderData['id']],
            [
                'order_number' => $orderData['id'],
                'name' => $orderData['name'],
                 'email' => $orderData['email'],
                'total_price' => $orderData['total_price'],
                'financial_status' => $orderData['financial_status'],
                'fulfillment_status' => $orderData['fulfillment_status'],
                'order_data' => json_encode($orderData),
                'store_id' => $store?->id,
                'created_at' => isset($order['created_at']) ? Carbon::parse($order['created_at']) : now(),
                'updated_at' => now(),

            ]
        );

        Log::info("Order created and saved from: {$shopDomain}");
    }

    protected function handleOrderUpdate(Request $request)
    {
        $orderData = $request->all();
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        Log::info("Webhook Order Update Data:", $orderData);
        $existingOrder = \App\Models\Order::where('order_number', $orderData['id'])->first();

        $incomingStatus = $orderData['fulfillment_status'] ?? null;
        $finalStatus = $incomingStatus !== null
            ? $incomingStatus
            : ($existingOrder->fulfillment_status ?? null);

        $normalizedShopUrl = 'https://' . $shopDomain;
        // Now match store using the full URL
        $store = \App\Models\Store::where('domain', $normalizedShopUrl)->first();
        if(!$store){
            return;
        }
        Log::info("Matched Store:", [$store]);


        \App\Models\Order::updateOrCreate(
            ['order_number' => $orderData['id']],
            [
                'name' => $orderData['name'],
                'email' => $orderData['email'] ?? null,
                'total_price' => $orderData['total_price'],
                'financial_status' => $orderData['financial_status'],
                'fulfillment_status' => $finalStatus,
                'order_data' => json_encode($orderData),
                'store_id' => $store?->id,
                'updated_at' => now(),
            ]
        );

        Log::info("Order updated from: {$shopDomain}");
    }

    protected function handleOrderDelete(array $payload)
    {
        $shopDomain = request()->header('X-Shopify-Shop-Domain');

        $normalizedShopUrl = 'https://' . $shopDomain;
        // Now match store using the full URL
        $store = \App\Models\Store::where('domain', $normalizedShopUrl)->first();
        if(!$store){
            return;
        }
        Log::info("Matched Store:", [$store]);
        $order = \App\Models\Order::where('order_number', $payload['id'])
            ->where('store_id', $store?->id)
            ->first();

        if ($order) {
            $order->delete(); // assuming your Order model uses SoftDeletes
            Log::info("Order {$payload['id']} soft-deleted from: {$shopDomain}");
        } else {
            Log::warning("Order not found for deletion: {$payload['id']} from {$shopDomain}");
        }
    }

    protected function handleOrderCancel(array $payload)
    {
        $shopDomain = request()->header('X-Shopify-Shop-Domain');

        $normalizedShopUrl = 'https://' . $shopDomain;
        // Now match store using the full URL
        $store = \App\Models\Store::where('domain', $normalizedShopUrl)->first();
        if(!$store){
            return;
        }
        Log::info("Matched Store:", [$store]);
        $order = \App\Models\Order::where('order_number', $payload['id'])
            ->where('store_id', $store?->id)
            ->first();

        if ($order) {
            $order->update([
                'financial_status' => $payload['financial_status'] ?? 'cancelled',
                'fulfillment_status' => $payload['fulfillment_status'] ?? 'cancelled',
                'order_data' => json_encode($payload),
                'updated_at' => now(),
            ]);

            Log::info("Order {$payload['id']} cancelled and updated from: {$shopDomain}");
        } else {
            Log::warning("Order not found for cancellation: {$payload['id']} from {$shopDomain}");
        }
    }

     protected function handleOrderRelease(array $payload)
    {   
           $data = isset($payload[0]) && is_array($payload[0]) ? $payload[0] : $payload;

            // Safely log fulfillment order GID
        $fulfillmentOrderGid = $data['fulfillment_order']['id'] ?? null;
        // Extract Fulfillment Order ID from GraphQL format
        $fulfillmentOrderGid = $data['fulfillment_order']['id'] ?? null;
        $fulfillmentOrderId = null;
        if ($fulfillmentOrderGid && preg_match('/FulfillmentOrder\/(\d+)/', $fulfillmentOrderGid, $matches)) {
            $fulfillmentOrderId = $matches[1];
        }

           $response = Http::withHeaders([
                'X-Shopify-Access-Token' => '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'
            ])->get("https://ds-demo-testing.myshopify.com/admin/api/2024-01/fulfillment_orders/{$fulfillmentOrderId}.json");

            $data = $response->json();

            $orderId = $data['fulfillment_order']['order_id'] ?? null;

       
        
        // Extract hold reason and notes
        $holdReason = $data['fulfillment_hold']['reason'] ?? 'unknown';
        $reasonNotes = $data['fulfillment_hold']['reason_notes'] ?? null;

        // Construct message
        if ($holdReason === 'other' && $reasonNotes) {
            $reasonMessage = "Hold Reason: Other - " . $reasonNotes;
        } elseif ($holdReason === 'other') {
            $reasonMessage = "Hold Reason: Other";
        } else {
            $reasonMessage = "Hold Reason: " . ucfirst($holdReason);
        }

         // Update the database
        // $update = OrderAction::where('order_id', $fulfillmentOrderId)->update([
        //     'decision_status'     => 'release_hold',  // <- set your intended status here
        //     'release_hold_reason' => $reasonMessage
        // ]);
        if($orderId){
            $action = OrderAction::create([
                'order_id'             => $orderId,
                'decision_status'      => 'release_hold',
                'release_hold_reason'  => $reasonMessage,
            ]);
        }else{
            Log::info("Order ID Not Exist");
        }
        // Log the result
        if ($action) {
            Log::info("OrderAction inserted: Order ID {$orderId} | Reason: {$reasonMessage}");
        } else {
            Log::warning("Failed to insert OrderAction for Order ID: {$orderId}");
        }
    }

    
}
