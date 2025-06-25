<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\UserLog;
use App\Models\Order;
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

    // public function handle(Request $request)
    // {

    //     // Verify the webhook
    //     $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
    //     $data = $request->getContent();

    //     // Log::info('hmacHeader-'.$hmacHeader);
    //     // Log::info('data-'.$data);
    //     // Log::info('accessToken-'.$this->accessToken .'-storeDomain-'. $this->storeDomain .'-apiSecret-'. $this->apiSecret);
    //     // Log::info('Shopify Webhook Unauthorized.');

    //     //$calculatedHmac = base64_encode(hash_hmac('sha256', $data, $this->apiSecret, true));

    //     /*

    //         Authorization code - not_for_delete
    //     if (!hash_equals($hmacHeader, $calculatedHmac)) {
    //         Log::warning('Invalid HMAC:', ['header' => $hmacHeader, 'calculated' => $calculatedHmac]);

    //         return response('Unauthorized', 401);
    //     }

    //     */

    //     // Log the payload for debugging
    //     //Log::info('Shopify Webhook Received:', ['payload' => $request->all()]);

    //     // Handle the webhook event
    //     $event      = $request->header('X-Shopify-Topic');
    //     $payload    = $request->all();

    //     // Log the payload for debugging
    //     //Log::info('Shopify Webhook Received:', ['payload' => $payload]);

    //     switch ($event) {
    //         case 'customers/create':
    //             $this->handleCustomerCreate($payload);
    //             break;

    //         case 'customers/update':
    //             $this->handleCustomerUpdate($payload);
    //             break;

    //         case 'orders/create':
    //             $this->handleOrderCreate($request);
    //             break;

    //         default:
    //             Log::warning("Unhandled Shopify event: $event");
    //     }

    //     return response('Webhook handled', 200);
    // }

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


    // protected function handleCustomerCreate(array $payload)
    // {

    //     try{

    //         $customerId = $payload['id']; // Get Order ID
    //         $lockKey = "customer_{$customerId}";

    //         // Attempt to acquire a lock for 20 seconds
    //         $lock = Cache::lock($lockKey, 20);

    //         $reward_list = array(
    //                 'M7 VIP Loyalty Program',
    //                 'ConiqLoyalty',
    //                 'WafiRewards',
    //             );



    //         if ($lock->get()) { // If lock is acquired, proceed

    //             try{

    //                 // Process customer signup data
    //                 Log::channel('signup')->info('New Customer Signup:', $payload);

    //                 // if(
    //                 //     !empty($payload['email'])
    //                 //     && ($payload['note'] == 'M7 VIP Loyalty Program' || $payload['note'] == 'ConiqLoyalty')
    //                 // )

    //                 $note = $payload['note'];

    //                 preg_match('/ConiqLoyalty:\s*(.*)/i', $note, $loyalty_matches);

    //                 $coniqLoyalty = isset($loyalty_matches[1]) ? trim($loyalty_matches[1]) : false;


    //                 if (in_array($note, $reward_list) || $coniqLoyalty)
    //                 {



    //                     $userExists = UserLog::where('email', $payload['email'])->first();

    //                     if(!empty($userExists)){

    //                         $user_data = $userExists->toArray();

    //                         if(!$user_data['marketing_agreement']){
    //                             $userExists->marketing_agreement = 1;
    //                             $userExists->save();

    //                             // Save to the database or perform other actions
    //                             $data = [
    //                                 'customer_id'   =>  $payload['id'],
    //                                 'first_name'    =>  $payload['first_name'],
    //                                 'last_name'     =>  $payload['last_name'],
    //                                 'email'         =>  $payload['email'],
    //                                 'phone'         =>  $payload['phone'],
    //                                 'action'        =>  'Customer Creation',
    //                                 'customer_created_at'  => Carbon::parse($payload['created_at']),
    //                                 'response'             => json_encode($payload),
    //                                 'marketing_agreement'  => 1
    //                             ];

    //                             if(isset($payload['addresses'][0]['country_code'])){
    //                                 $data['country_isd'] = getCountryISDByCode($payload['addresses'][0]['country_code']);
    //                             }else if(!empty($payload['country_isd'])){
    //                                 $data['country_isd'] = $payload['country_isd'];
    //                             }

    //                             $this->shopifyService->coniqSignup($data);

    //                         }

    //                         return true;
    //                     }else{

    //                         $marketing_agreement = 0;

    //                         // if(
    //                         //     $payload['note'] == 'M7 VIP Loyalty Program'
    //                         //     || $payload['note'] == 'ConiqLoyalty'
    //                         // )

    //                         if (in_array($note, $reward_list) || $coniqLoyalty)
    //                         {
    //                             $marketing_agreement = 1;
    //                         }

    //                         try{

    //                             // Save to the database or perform other actions
    //                             $data = [
    //                                 'customer_id'   =>  $payload['id'],
    //                                 'first_name'    =>  $payload['first_name'],
    //                                 'last_name'     =>  $payload['last_name'],
    //                                 'email'         =>  $payload['email'],
    //                                 'phone'         =>  $payload['phone'],
    //                                 'action'        =>  'Customer Creation',
    //                                 'customer_created_at'  => Carbon::parse($payload['created_at']),
    //                                 'response'             => json_encode($payload),
    //                                 'marketing_agreement'  => intval($marketing_agreement)
    //                             ];

    //                             if(isset($payload['addresses'][0]['country_code'])){
    //                                 $data['country_isd'] = getCountryISDByCode($payload['addresses'][0]['country_code']);
    //                             }else if(!empty($payload['country_isd'])){
    //                                 $data['country_isd'] = $payload['country_isd'];
    //                             }

    //                             UserLog::create($data);

    //                             $this->shopifyService->coniqSignup($data);

    //                         }catch (\Exception $e) {
    //                             Log::channel('signup')->info('handleCustomerCreate signup failed.', [
    //                                 'error' => $e->getMessage(),
    //                                 'payload' => $payload,
    //                             ]);
    //                         }

    //                     }
    //                 }
    //             }finally{
    //                 // Ensure the lock is released after processing
    //                 if (isset($lock) && $lock->get()) { 
    //                     $lock->release();
    //                 } 
    //             }

    //         } else {
    //             Log::channel('signup')->info("Duplicate request detected for signup: $customerId, skipping...");
    //         }
    //     }catch(\Exception $e) {
    //         Log::channel('signup')->info('cache locking failed.', [
    //             'error' => $e->getMessage(),
    //             'payload' => $payload,
    //         ]);
    //     }
    // }

    // public function registerOrderUpdatedWebhook($shopDomain, $accessToken)
    // {
    //     $apiVersion = '2024-10'; // Use latest stable version

    //     $response = Http::withHeaders([
    //         'X-Shopify-Access-Token' => $accessToken,
    //         'Content-Type' => 'application/json',
    //     ])->post("https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json", [
    //         'webhook' => [
    //             'topic'   => 'orders/updated',
    //             'address' => 'https://yourdomain.com/api/shopify/webhook', // Replace with your real webhook handler
    //             'format'  => 'json',
    //         ]
    //     ]);

    //     if ($response->successful()) {
    //         return response()->json(['success' => true, 'data' => $response->json()]);
    //     }

    //     return response()->json([
    //         'success' => false,
    //         'error' => $response->body()
    //     ], $response->status());
    // }


    public function registerAllOrderWebhooks($shopDomain, $accessToken)
    {
        $apiVersion = '2024-10'; // Update as needed
        $baseUrl = "https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json";

        $webhooks = [
            'orders/create'    => 'https://yourdomain.com/api/shopify/webhook',
            'orders/updated'   => 'https://yourdomain.com/api/shopify/webhook',
            'orders/delete'    => 'https://yourdomain.com/api/shopify/webhook',
            'orders/cancelled' => 'https://yourdomain.com/api/shopify/webhook',
        ];

        $results = [];

        foreach ($webhooks as $topic => $callbackUrl) {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type'           => 'application/json',
            ])->post($baseUrl, [
                'webhook' => [
                    'topic'   => $topic,
                    'address' => $callbackUrl,
                    'format'  => 'json',
                ]
            ]);

            $results[$topic] = $response->successful()
                ? ['success' => true, 'webhook' => $response->json()]
                : ['success' => false, 'error' => $response->body()];
        }

        return response()->json($results);
    }


    public function registerWebhooksForAllStores()
    {
        $stores = \App\Models\Store::all();
        $results = [];

        foreach ($stores as $store) {
            $results[$store->domain] = $this->registerAllOrderWebhooks($store->domain, $store->token)->getData();
        }

        return response()->json($results);
    }



    protected function handleCustomerUpdate(array $payload)
    {
        // Process customer update (e.g., login or profile change)
        Log::info('Customer Update:', $payload);
        // Save changes to the database or perform other actions
    }

    protected function handleProductsUpdate(array $payload)
    {
        // Process customer update (e.g., login or profile change)
        Log::info('Product Update:', $payload);
        // Save changes to the database or perform other actions
    }

    // public function handleOrderCreate(array $payload){
    //     if(!isOrderExists($payload['id'])){
    //         Log::channel('loyalty')->info('Order Create:', $payload);
    //         $this->loyaltyTransaction($payload);
    //     }

    //     return true;
    // }



    public function handleSubscription(Request $request)
    {

        //die('Test2');
        //$this->shopifyService->subscriptionRetrieveData();
    }

    public function handleTransaction(Request $request)
    {
        /*
            https://api-stage.coniq.com/create-transaction

            {
                "barcode" : "2434083078612346", // Required
                "location_id" : "75609",        // Required
                "amount" : "1.0",               // Required
                "offer_id" : "26953"            // Optional
            }
        
        */
    }


    public function showDiscount()
    {
        /*
            1. Get customer data by id from laravel database
            2. Get customer data by customer email from coniq
                https://api.coniq.com/subscription?customer_email=test@gmail.com&offer_id=45038
            3. Get Barcode by customer email
                https://api.coniq.com/barcode?customer_email=test@gmail.com
            4.
        */
    }

    public function showLoyalty(Request $request)
    {

        $response = [
            'status' => 'error',
            'message' => 'Something went wrong! Please try again later.'
        ];

        $customer_email = !empty($request->email) ? $request->email : '';


        if (!empty($customer_email)) {
            $subscription = $this->shopifyService->getSubscription($customer_email);

            if (!empty($subscription)) {
                $response['status']       = 'success';
                $response['loyalty']        = [
                    'title' => $this->coniqLoyaltyTitle,
                    'logo'  => $this->coniqLoyaltyLogo,
                ];
                $response['subscription'] = $subscription;
                $response['discounts']    = $this->getUserDiscounts($request);
                $response['message']      = 'Data is retrieve successfully.';
            } else {
                $response['message'] = 'You are not subscribe the loyalty program.';
                $response['loyalty'] = [
                    'title' => $this->coniqLoyaltyTitle,
                    'logo'  => $this->coniqLoyaltyLogo,
                ];
            }
        }


        return $response;
    }


    public function draftOrderCreateGraphQL(Request $request)
    {

        try {

            $verify_response = $this->shopifyService->validDiscount($request);
            if ($verify_response['status'] != 'success') {
                throw new \Exception($verify_response['message']);
            }

            // GraphQL query and variables
            $query          = getDraftOrderMutation();
            $variables      = $request->variables;
            $extra_params   = $request->extra_params;

            /*
                Commented Code For Research Not For Delete:
                $variables = [
                    "input" => [
                        "customerId" => "gid://shopify/Customer/8461155270849",
                        "lineItems" => [
                            [
                                "variantId" => "gid://shopify/ProductVariant/48840066334913",
                                "quantity" => 1
                            ]
                            ],
                        "appliedDiscount" =>[
                                "valueType"=>"FIXED_AMOUNT",
                                "value" => 20,
                                "title" => "USRA Rewards"

                            ]
                    ]
                ];
            */

            // Prepare cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiGraphQLUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "X-Shopify-Access-Token: $this->accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "query" => $query,
                "variables" => $variables
            ]));

            // Execute cURL request
            $response = curl_exec($ch);


            // Check for errors
            // if (curl_errno($ch)) {
            //     $response['status']     = 'error';
            //     $response['message']    = curl_error($ch);
            // } else {
            //     $response['status'] = 'success';

            //     return json_encode($response);
            // }



            // Close cURL session
            curl_close($ch);

            $this->saveOrder($response, $variables, $extra_params);

            Log::channel('loyalty')->info('Draft Order Create.', [
                'response'      =>  $response,
                'variables'     =>  $variables,
                'extra_params'  =>  $extra_params
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Failed Create Draft Order.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /* Update draft order
       Purpuse: If customer change its discount amount then change
    */
    public function draftOrderUpdateGraphQL(Request $request)
    {

        try {

            $verify_response = $this->shopifyService->validDiscount($request);
            if ($verify_response['status'] != 'success') {
                throw new \Exception($verify_response['message']);
            }
            /*
                Commented Code For Research Not For Delete:
                $variables = [
                    "id" => "gid://shopify/DraftOrder/1183306121409",
                    "input" => [
                        "appliedDiscount" =>[
                                "valueType"=>"FIXED_AMOUNT",
                                "value" => 20,
                                "title" => "M7 LOYALTY DISCOUNT to USRA Voucher"
                        ],
                    ],
                ];
            */

            // GraphQL query and variables
            $query         = getUpdateDraftOrderMutation();
            $variables     = $request->variables;
            $extra_params  = $request->extra_params;

            if (empty($query) || empty($variables)) {
                $response['status']     = 'error';
                $response['message']    = 'Invalid parameters.';
                return $response;
            }

            /* Prepare cURL request */
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiGraphQLUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "X-Shopify-Access-Token: $this->accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "query" => $query,
                "variables" => $variables
            ]));

            // Execute cURL request
            $response = curl_exec($ch);

            /* Close cURL session */
            curl_close($ch);

            //$decoded_response = json_decode($response, true);
            // if (isset($decoded_response['errors'])) {
            //     throw new \Exception("Shopify GraphQL error: " . json_encode($decoded_response['errors']));
            // }

            $this->updateOrder($response, $variables, $extra_params);

            return $response;
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Failed Update Draft Order.', [
                'error' => $e->getMessage(),
                'payload' => $request,
            ]);
        }
    }


    public function saveOrder($response, $variables, $extra_params)
    {


        try {

            $order_data = json_decode($response, true);

            $order_info = $order_data['data']['draftOrderCreate']['draftOrder'];

            /*
                Commented Order Data for Testing

                $order_info = [
                    'id' => 'gid://shopify/DraftOrder/1183491064001',
                    'customer'=>[
                        'id'=>'gid://shopify/Customer/8474572914881',
                        'email' => 'developer.dotsquares2@gmail.com',
                        'firstName'=> 'Ray',
                        'lastName' => 'carter',
                    ],
                    'currencyCode'=>'GBP',
                ];
                $extra_params['rule']['rule_id'] = 4321;
                $variables['input']['appliedDiscount']['value'] = 20;
            */

            $orderObj       = new Order();
            $loyalityObj    = new Loyality();

            if (!empty($extra_params['amount'])) {
                $extra_params['amount']  = $this->shopifyService->getAmount($extra_params['amount']);
            }

            $order_number            = getLastIntegerFromGid($order_info['id']);
            $customer_id             = getLastIntegerFromGid($order_info['customer']['id']);
            $orderObj->order_number  = $order_number;
            $orderObj->customer_id   = $customer_id;
            $orderObj->customer_name = $order_info['customer']['firstName'] . ' ' . $order_info['customer']['lastName'];
            $orderObj->customer_email = $order_info['customer']['email'];
            $orderObj->currency      = $order_info['currencyCode'];
            $orderObj->total_amount  = !empty($extra_params['amount']) ? $extra_params['amount'] : 0.00;



            $orderObj->save();

            $loyalityObj->order_id          = $orderObj->id;
            $loyalityObj->order_number      = $order_number;
            $loyalityObj->customer_id       = $customer_id;
            $loyalityObj->customer_name     = $order_info['customer']['firstName'] . ' ' . $order_info['customer']['lastName'];
            $loyalityObj->customer_email    = $order_info['customer']['email'];
            $loyalityObj->rule_id           = $extra_params['rule']['rule_id'];
            $loyalityObj->order_amount      = !empty($extra_params['amount']) ? $extra_params['amount'] : 0.00;
            $loyalityObj->discount_amount   = $variables['input']['appliedDiscount']['value'];

            $loyalityObj->save();
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Save order failed.', [
                'error' => $e->getMessage(),
                'order_data' => $order_data,
            ]);
        }
    }

    public function updateOrder($response, $variables, $extra_params)
    {


        try {


            $order_number                   = getLastIntegerFromGid($variables['id']);

            $loyalityObj                    = Loyality::where('order_number', $order_number)->firstOrFail();

            $loyalityObj->rule_id           = $extra_params['rule']['rule_id'];
            $loyalityObj->discount_amount   = $variables['input']['appliedDiscount']['value'];

            $loyalityObj->save();
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Update order failed.', [
                'error' => $e->getMessage(),
                'response' => $response,
            ]);
        }
    }




    public function getUserDiscounts(Request $request)
    {

        $customer_email = !empty($request->email) ? $request->email : '';
        $amount         = !empty($request->amount) ? $request->amount : 0;


        if (empty($customer_email) || $amount <= 0) {
            $response['status']     = 'error';
            $response['message']    = 'Invalid parameters.';
            return $response;
        }

        $barcode = $this->shopifyService->getBarcode($customer_email);

        $params['barcode']  = $barcode[0]['barcode_number'];
        $params['amount']   = $amount;


        $rules = $this->shopifyService->transactionAvailableRules($params);

        $length = count($rules['spend_voucher_rules']);
        if ($length > 0) {
            $spend_voucher_rules = $rules['spend_voucher_rules'];
            $sort = array();
            foreach ($spend_voucher_rules as $key => $value) {
                $sort['points_required'][$key]    = $value['points_required'];
                $sort['rule_id'][$key]            = $value['rule_id'];

                $spend_voucher_rules[$key]['discount_amount']    = $this->shopifyService->getDiscountByStore($value['discount_amount']);
            }
            # It is sorted by event_type in descending order and the title is sorted in ascending order.
            array_multisort($sort['points_required'], SORT_DESC, $spend_voucher_rules);
            $vouchers = $spend_voucher_rules;
        } else {
            $vouchers = [];
        }

        return $vouchers;
    }

    /* This payload is order json,  get by order creation webhook */
    public function loyaltyTransaction($payload)
    {

        try {

            //$subscrition = $this->shopifyService->getSubscription($payload['customer']['email']);
            //if(empty($subscrition)){

            $orderId = $payload['id']; // Get Order ID
            $lockKey = "loyalty_transaction_{$orderId}";

            // Attempt to acquire a lock for 20 seconds
            $lock = Cache::lock($lockKey, 20);

            if ($lock->get()) { // If lock is acquired, proceed

                //if(in_array($this->storeDomain, ['wafi-link.myshopify.com']))
                // if($this->shopifyService->isLinkStore()){
                //     /* Linkstore (wafi-link) send all orders as anonymous transaction */
                //     //return $this->anonymousTransaction($payload);
                //     return $this->withoutLoyaltyTransaction($payload);
                //     return true;
                // }

                try {

                    if (!isLoyaltyUserExists($payload['customer']['email'])) {

                        $subscription = $this->shopifyService->getSubscription($payload['customer']['email']);
                        if (!empty($subscription)) {
                            if (empty($payload['customer']['first_name'])) {
                                $first_name = explode('@', $payload['customer']['email'])[0];
                            } else {
                                $first_name = $payload['customer']['first_name'];
                            }

                            $data = [
                                'id'         =>  $payload['customer']['id'],
                                'first_name' =>  $first_name,
                                'last_name'  =>  !empty($payload['customer']['last_name']) ? $payload['customer']['last_name'] : 'LName',
                                'email'      =>  $payload['customer']['email'],
                                'phone'      =>  $this->shopifyService->getPhoneNumber($payload),
                                'country_isd' =>  getCountryISDByCode($payload['billing_address']['country_code']),
                                'note'       => 'ConiqLoyalty',
                                'created_at' =>  $payload['customer']['created_at'],
                            ];

                            $response = $this->handleCustomerCreate($data);
                        } else {

                            if (!empty($payload['note_attributes'])) {

                                foreach ($payload['note_attributes'] as $attribute) {
                                    if ($attribute['name'] == 'ConiqLoyalty' && $attribute['value'] == 'AppliedConiqLoyalty') {

                                        if (empty($payload['customer']['first_name'])) {
                                            $first_name = explode('@', $payload['customer']['email'])[0];
                                        } else {
                                            $first_name = $payload['customer']['first_name'];
                                        }

                                        $data = [
                                            'id'         =>  $payload['customer']['id'],
                                            'first_name' =>  $first_name,
                                            'last_name'  =>  !empty($payload['customer']['last_name']) ? $payload['customer']['last_name'] : 'LName',
                                            'email'      =>  $payload['customer']['email'],
                                            'phone'      =>  $this->shopifyService->getPhoneNumber($payload),
                                            'country_isd' =>  getCountryISDByCode($payload['billing_address']['country_code']),
                                            'note'       => 'ConiqLoyalty',
                                            'created_at' =>  $payload['customer']['created_at'],
                                        ];

                                        $response = $this->handleCustomerCreate($data);
                                        Log::channel('loyalty')->info('Calling withoutLoyaltyTransaction when user signup on coniq.');
                                        return $this->withoutLoyaltyTransaction($payload);
                                        break;
                                    }
                                }
                            }

                            if ($this->shopifyService->isLinkStore()) {
                                return $this->withoutLoyaltyTransaction($payload);
                            } else {
                                /* Not subscribe person called as anonymous transaction */
                                return $this->anonymousTransaction($payload);
                            }
                        }
                    }


                    if ($this->shopifyService->isLinkStore()) {
                        /*  Linkstore (wafi-link) send all orders as anonymous transaction 
                                But anonymous transaction is not working, so add withoutLoyaltyTransaction function
                            */
                        //return $this->anonymousTransaction($payload);
                        return $this->withoutLoyaltyTransaction($payload);
                        return true;
                    }

                    $draft_order_number = $this->getDraftOrderByOrderId($payload['id'], $payload['customer']['id']);

                    if (empty($draft_order_number)) {
                        /* Insert with loyalty data */
                        Log::channel('loyalty')->info('Calling withoutLoyaltyTransaction when draft order not found.');
                        $this->withoutLoyaltyTransaction($payload);
                        return true;
                    }

                    $loyality = Loyality::where('order_number', $draft_order_number)->first();

                    if (empty($loyality) && $loyality->status != 'pending') {

                        Log::channel('loyalty')->info('Calling withoutLoyaltyTransaction when loyalty record not found.');
                        $this->withoutLoyaltyTransaction($payload);
                        return true;
                    } else {
                        $order = Order::where('id', $loyality->order_id)->first();

                        /* Order Number save here because rest code take time to execution so after order number save it will check on handleOrderCreate function */
                        $order->order_number = $payload['id'];
                        $order->response     = json_encode($payload);
                        $order->save();


                        /*

                            Testing Code:

                            $request->merge([
                                'barcode' => 2430956599891351,
                                'amount'   => 629.95,
                                'rule_id' => 4341,
                            ]);
                            
                            
                            $params['barcode'] = (!empty($request->barcode)) ? $request->barcode : '';
                            $params['amount']  = (!empty($request->amount))  ? $request->amount   : '';
                            $params['rule']    = (!empty($request->rule_id)) ? $request->rule_id  : '';

                            */

                        $barcode_data = $this->shopifyService->getBarcode($loyality->customer_email);

                        if (empty($barcode_data)) {
                            throw new \Exception('User ' . $loyality->customer_email . ' unable to get barcode.');
                        }

                        $order_amount  = $this->shopifyService->getAmount($payload[$this->amount_key]);

                        $params['barcode']  = $barcode_data[0]['barcode_number'];
                        $params['amount']   = $order_amount;
                        $params['rule']     = $loyality->rule_id;
                        $params['type']     = 'spend';


                        $verify_response    = $this->shopifyService->verifyTransaction($params);
                        $ct_response        = $this->shopifyService->createTransaction($params);

                        if (isset($ct_response['success']) && $ct_response['success'] == 1) {

                            $order = Order::where('id', $loyality->order_id)->first();

                            $order->total_amount            = $order_amount;
                            $order->transaction_external_id = $ct_response['transaction_id'];
                            $order->avail_loyalty           = 1;
                            $order->status                  = 'completed';
                            $order->save();

                            $loyality->order_number            = $payload['id'];
                            $loyality->order_amount            = $order_amount;
                            $loyality->transaction_external_id = $ct_response['transaction_id'];
                            $loyality->status = 'completed';
                            $loyality->save();
                        }
                    }

                    Log::channel('loyalty')->info('Coniq Transaction Response.', [
                        'payload' => $payload,
                        'response' => $ct_response,
                        'coniq_params' => $params,
                    ]);

                    return true;
                } finally {
                    $lock->release(); // Ensure the lock is released after processing
                }
            } else {
                Log::channel('loyalty')->info("Duplicate request detected for order: $orderId, skipping...");
            }
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Save Transaction failed.', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }


    public function anonymousTransaction($payload)
    {

        /*
            Testing Array
            
            $payload = [
                'id' => '6243987751105',
                'customer'=>[
                    'id'=>'8500961181889',
                    'email' => 'guestuser2@gmail.com',
                    'first_name'=> 'Ray',
                    'last_name' => 'carter',
                ],
                'current_total_price_set'=>[
                    'shop_money'=>[
                        'amount'=>629
                    ]
                ],
                'currency'=>'GBP',
            ];
        */


        try {



            $orderObj = new Order();

            //$total_line_items_price  = $this->shopifyService->getAmount($payload['total_line_items_price']);
            $order_amount  = $this->shopifyService->getAmount($payload[$this->amount_key]);

            $orderObj->order_number  = $payload['id'];
            $orderObj->customer_id   = $payload['customer']['id'];
            $orderObj->customer_name = $payload['customer']['first_name'] . ' ' . $payload['customer']['last_name'];
            $orderObj->customer_email = $payload['customer']['email'];
            $orderObj->currency      = $payload['currency'];
            $orderObj->total_amount  = $order_amount;
            $orderObj->response      = json_encode($payload);
            $orderObj->m7_marketing  = 0;
            $orderObj->avail_loyalty = 0;
            $orderObj->status        = 'completed';

            $orderObj->save();


            $params['amount']       = $order_amount;
            $anonymous_transaction  = $this->shopifyService->anonymousTransaction($params);

            if (isset($anonymous_transaction['success']) && $anonymous_transaction['success'] == 1) {

                $orderObj->transaction_external_id  = $anonymous_transaction['transaction_id'];

                $orderObj->save();
            }

            Log::channel('loyalty')->info('Anonymous Transaction & Save Order.', [
                'payload' => $payload,
                'api_response' => $anonymous_transaction
            ]);
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Anonymous Transaction & Save Order failed.', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }


        return true;
    }


    public function withoutLoyaltyTransaction($payload)
    {

        try {



            $orderObj = new Order();

            $order_amount  = $this->shopifyService->getAmount($payload[$this->amount_key]);

            $orderObj->order_number  = $payload['id'];
            $orderObj->customer_id   = $payload['customer']['id'];
            $orderObj->customer_name = $payload['customer']['first_name'] . ' ' . $payload['customer']['last_name'];
            $orderObj->customer_email = $payload['customer']['email'];
            $orderObj->currency      = $payload['currency'];
            $orderObj->total_amount  = $order_amount;
            $orderObj->response      = json_encode($payload);
            $orderObj->m7_marketing  = 1;
            $orderObj->avail_loyalty = 0;
            $orderObj->status        = 'completed';

            $orderObj->save();

            $barcode_data = $this->shopifyService->getBarcode($payload['customer']['email']);

            if (empty($barcode_data)) {
                throw new \Exception('User ' . $payload['customer']['email'] . ' unable to get barcode.');
            }

            $params['barcode'] = $barcode_data[0]['barcode_number'];
            $params['amount']  = $order_amount;

            $apiResponse = [];

            $verify_response    = $this->shopifyService->verifyTransaction($params);
            $apiResponse = $verify_response;

            if (isset($verify_response['success']) && $verify_response['success']) {

                $ct_response        = $this->shopifyService->createTransaction($params);

                if (isset($ct_response['success']) && $ct_response['success']) {

                    $orderObj->transaction_external_id = $ct_response['transaction_id'];
                    $orderObj->save();
                }
                $apiResponse = $ct_response;
            }

            Log::channel('loyalty')->info('Without Loyalty Transaction & Save Order.', [
                'payload' => $payload,
                'response' => $apiResponse
            ]);
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Without Loyalty Transaction & Save Order failed.', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }


        return true;
    }



    /*
        Purpose: Order id related draft order record because currenlty available way is
        find order id by draft order so i need to check previous 5 pending
        order that related to that customer
    */
    public function getDraftOrderByOrderId($order_id, $customer_id)
    {

        $draft_orders = Order::where('customer_id', $customer_id)
            ->where('status', 'pending')
            ->latest('id')
            ->limit(5)
            ->get()->toArray();

        if (!empty($draft_orders)) {
            foreach ($draft_orders as $draft_order) {
                $data = $this->isDraftOrderCompleted($draft_order['order_number']);

                if ($data['status'] == 'success' && $data['order_id'] == $order_id) {
                    return $draft_order['order_number'];
                }
            }
        }

        return false;
    }

    public function isDraftOrderCompleted($draft_order_id)
    {


        $response =  [
            'status' => 'error',
            'message' => 'No completed draft order found for the given order ID.'
        ];



        // GraphQL query
        $query  =   'query getDraftOrder($id: ID!) {
                        draftOrder(id: $id) {
                            id
                            name
                            status
                            order {
                                id
                                name
                            }
                            totalPriceSet {
                                presentmentMoney {
                                    amount
                                }
                            }
                        }
                    }';

        // GraphQL variables
        $variables = [
            'id' => 'gid://shopify/DraftOrder/' . $draft_order_id, // Adjust this value as needed
        ];

        // Prepare cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiGraphQLUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $this->accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'query' => $query,
            'variables' => $variables
        ]));

        // Execute cURL request
        $curl_response = curl_exec($ch);
        curl_close($ch);

        // Decode the response
        $data = json_decode($curl_response, true);


        // Check if the response contains draft orders
        if (
            isset($data['data']['draftOrder']['status'])
            &&  $data['data']['draftOrder']['status'] == 'COMPLETED'
            &&  !empty($data['data']['draftOrder']['order']['id'])
        ) {

            $response =  [
                'status'    => 'success',
                'order_id'  => getLastIntegerFromGid($data['data']['draftOrder']['order']['id']),
            ];
        }

        return $response;
    }

    /*
        This function only for testing admin api
    
        public function draftOrderCreate(Request $request) {
        
        $client_id      = '64e55c2453ea2b2dbc47f348c2a4e657';
        $client_secret  = '6e0c619c983c420e48272c72fe4cfdfa';
        
        $response = Http::post("https://gant-devp.myshopify.com/admin/oauth/access_token", [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ]);

        $response1 = $response->json();
        pr($response1);
        die;
        
        $body = [
            "draft_order" => array(
               "applied_discount" =>array (
                   "value_type"=>"fixed_amount",
                   "value" => 10,
                   "description" => "CASH REWARD"

               ),
            
            "line_items" => [
                    array(
                    //  "id"=> $id,
                        "variant_id"=> '48840066334913',
                        "product_id"=> '10621071622337',
                        "title"=>'The Multi-managed Snowboard',
                        "price" =>629.95,
                        "quantity"=>1
                    )
                ]
            )
        ];

        $key = [
            '4f664132ae496320e390fb0f5eec4901'          => '4f664132ae496320e390fb0f5eec4901',
            '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'    => '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'
        ];

        $discount_order = Http::withHeaders($key)->post('https://4f664132ae496320e390fb0f5eec4901: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@gant-devp.myshopify.com/admin/api/2024-10/draft_orders.json',
            $body,true);

        pr($discount_order->json());
        die;
    }
        
    */

    public function handleTest(Request $request)
    {

        // Verify the webhook
        //$hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
        //$data = $request->getContent();
        $data = file_get_contents('php://input');

        Log::info('hmacHeader-' . $hmacHeader);



        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $this->clientSecret, true));

        if (!hash_equals($hmacHeader, $calculatedHmac)) {
            Log::info('Invalid HMAC:', ['header' => $hmacHeader, 'calculated' => $calculatedHmac]);

            return response('Unauthorized', 200);
        }

        // Handle the webhook event
        $event      = $request->header('X-Shopify-Topic');
        $payload    = $request->all();

        Log::info("HMAC Passed:", $payload);

        return response('Webhook handled', 200);
    }

    public function testRecord()
    {

        $test = isset($_GET['test']) ? $_GET['test'] : '';

        if ($test == 'loyalty') {
            //-- Get Loyalty
            $request = request();
            $request->merge([
                //'email' => 'strikavalentinaa@gmail.com',
                //'email' => 'tshaban@yahoo.com',
                'email' => 'andaluz.elaine@gmail.com',
                'amount'   => 49,
            ]);

            $data = $this->showLoyalty($request);
        } else if ($test == 'create_transaction') {
            //--Deduct Point / create transaction
            $request = request();
            $request->merge([
                //'customer_email' => 'strikavalentinaa@gmail.com',
                'customer_email' => 'andaluz.elaine@gmail.com',
            ]);

            $barcode_data = $this->shopifyService->getBarcode($request->customer_email);

            $params['barcode']  = $barcode_data[0]['barcode_number'];
            $params['amount']   = 49;
            $params['rule']     = 4353;
            $params['type']     = 'spend';

            //$data        = $this->shopifyService->createTransaction($params);
        }






        //pr($data);

        return true;
        $data = [
            'first_name' =>  '',
            'last_name'  =>  '',
            'email'      =>  'dstest@dotsquares.com',
            'marketing_agreement'  =>  1,
            'marketing_channels'   => [
                'email' => 1,
                'sms'  => 1
            ]
        ];

        //$response = $this->shopifyService->coniqSignup($data);
        $response = $this->shopifyService->getCustomerDataByEmail('developer.dotsquares2@gmail.com');
        pr($response);
        die;
    }
}
