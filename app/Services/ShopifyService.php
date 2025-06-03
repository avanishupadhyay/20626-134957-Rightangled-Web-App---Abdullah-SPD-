<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ShopifyService
{
    protected $client;
    protected $storeDomain;
    protected $accessToken;
    //protected $apiSecret;
    protected $siteEnvironment;
    //protected $apiKey;
    protected $coniqSignupUrl;
    protected $coniqApiUrl;
    protected $coniqApiKey;
    protected $coniqHash;
    protected $coniqOfferID;
    protected $coniqLocationID;

    public function __construct()
    {
        $this->client = new Client();
        
        
        $this->siteEnvironment  = config('Site.environment');

        $this->accessToken      = config('Shopify.access_token');
        $this->storeDomain      = config('Shopify.api_host');
        
        $this->coniqHash        = config('Coniq.hash');
        $this->coniqApiKey      = config('Coniq.api_key');
        $this->coniqOfferID     = config('Coniq.offer_id');
        $this->coniqLocationID  = config('Coniq.location_id');
        
        if($this->siteEnvironment == 'production'){
            $this->coniqSignupUrl   = 'https://poweredby.coniq.com';
            $this->coniqApiUrl      = 'https://api.coniq.com';
        }else{
            $this->coniqSignupUrl = 'https://poweredby-stage.coniq.com';
            $this->coniqApiUrl    = 'https://api-stage.coniq.com';
        }
        
    }

    public function getProducts()
    {
        try {
            $response = $this->client->request('GET', "https://{$this->storeDomain}/admin/api/2024-01/products.json", [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getHeaders(){
        return [
            'Authorization' => 'ApiKey key="'.$this->coniqApiKey.'"',
            'Content-type'=> 'application/json',
            'x-api-version' =>'2.0'
        ];
    }

    

    public function isLinkStore(){
        return (in_array($this->coniqLocationID, ['96195','96938'])) ? TRUE : FALSE;
    }
    
    public function isGantKuwaitStore(){
        return (in_array($this->coniqLocationID, ['96187','96807'])) ? TRUE : FALSE;
    }

    public function getAmount($amount){
        if($this->isGantKuwaitStore()) {
            /* This condition for gant.com.kw */
            $number = $amount * 12;
            return number_format($number, 2, '.', '');
        }else{
            return $amount;
        }
    }

    public function getDiscountByStore($discount){
        if($this->isGantKuwaitStore()) {
            /* This condition for gant.com.kw */
            $discount = $discount / 12;
            return round($discount);
        }else{
            return $discount;
        }
    }

    public function getPhoneNumber($payload){
        $phone = '7123456789';
        if(!empty($payload['customer']['phone'])) {
            $phone = $payload['customer']['phone'];
        }else if(!empty($payload['customer']['default_address']['phone'])) {
            $phone = $payload['customer']['default_address']['phone'];
        }else if(!empty($payload['billing_address']['phone'])) {
            $phone = $payload['billing_address']['phone'];
        }else if(!empty($payload['shipping_address']['phone'])) {
            $phone = $payload['shipping_address']['phone'];
        }

        return $phone;
    }

    public function coniqSignup($data)
    {
        if($data['marketing_agreement'] != 1){
            return false;
        }

        if($data['marketing_agreement'] == 1){

            $endpoint = $this->coniqHash.'.json';

            $fields['fields'] = [
                'first_name' =>  $data['first_name'],
                'last_name'  =>  $data['last_name'],
                'email'      =>  $data['email'],
                'marketing_agreement'  =>  $data['marketing_agreement'],
                /*
				'marketing_channels'   => [
                    'email'=>1,
                    'sms'  =>1
                ]
				*/
            ];

        }else{

            $endpoint = $this->coniqHash.'.json';

            $fields['fields'] = [
                'first_name' =>  $data['first_name'],
                'last_name'  =>  $data['last_name'],
                'email'      =>  $data['email'],
                'marketing_agreement'  =>  $data['marketing_agreement'],
            ];
        }
        
        
        $fields['fields']['phone'] = ['country_code'=>'44','number'=>'7123456789'];
        if(!empty($data['country_isd'])){
            $fields['fields']['phone']['country_code'] = $data['country_isd'];
        }

        if(!empty($data['phone'])){
            $fields['fields']['phone']['number'] = $data['phone'];
        }

        //$fields['fields']['external_id']       = $data['customer_id'];
        $fields['fields']['privacy_agreement'] = 1;
        
        //$fields['fields']['preferred_location_group'] = '24444';
        
        if($this->isLinkStore()){
            unset($fields['fields']['marketing_agreement']);
            unset($fields['fields']['external_id']);
            unset($fields['fields']['marketing_channels']);
        }
        
        try {

            $headers = $this->getHeaders();

            $response = $this->client->post($this->coniqSignupUrl .'/signup'.'/'. $endpoint, [
                'headers' => $headers,
                'json' => $fields,
            ]);
        
            // Parse the API response
            $responseBody = json_decode($response->getBody(), true);
        
            // Prepare payload for logging
            $logPayload = [
                'payload' => $fields,
                'api_name' => $endpoint,
                'response' => $responseBody,
            ];

            Log::channel('signup')->info('Signup API Call Successfully', $logPayload);
        
        } catch (\Exception $e) {
            Log::channel('signup')->info('Signup API Call Failed', [
                'error' => $e->getMessage(),
                'api_name' => $endpoint,
                'payload' => $fields,
            ]);
        }

    }

    public function getSubscription($customer_email)
    {
        try {

            $headers = $this->getHeaders();

            $params = [
                'customer_email' => $customer_email,
                'offer_id'       => $this->coniqOfferID,  //loyalty_id RND check
            ];

            // Make the GET request with headers and query params
            //$url = $this->coniqApiUrl . '/subscription?customer_email='.$customer_email.'&offer_id='.$this->coniqOfferID;
            
            $custom_data = Http::withHeaders($headers)->get($this->coniqApiUrl . '/subscription', $params)->json();

            return !empty($custom_data[0]) ? $custom_data[0] : [];
            
        } catch (\Exception $e) {
            Log::info('Subscription Retrieve API Call Failed', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }



    public function getBarcode($customer_email)
    {
        try {

            $headers = $this->getHeaders();

            $params['customer_email'] = $customer_email;
            $params['offer_id']       = $this->coniqOfferID;
            
            // Make the GET request with headers and query params
            
            $barcode = Http::withHeaders($headers)->get($this->coniqApiUrl . '/barcode', $params)->json();

            return $barcode;
            
        } catch (\Exception $e) {
            Log::info('Customer barcode retrieve API call failed.', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }


    /*
        To show offer amount according to price.
    */
    public function transactionAvailableRules(array $params)
    {
        try {

            $headers = $this->getHeaders();

            $params['offer_id']     = $this->coniqOfferID;
            $params['location_id']  = $this->coniqLocationID;

            if(!empty($params['amount'])){
                $params['amount']       = $this->getAmount($params['amount']);
            }
            

            // Make the GET request with headers and query params
            $available_rules = Http::withHeaders($headers)->post($this->coniqApiUrl . '/transaction/availableRules', $params)->json();

            return $available_rules;
            
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('Availables offer retrieve API call failed.', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }


    /*
        To verify transaction.
    */
    public function verifyTransaction(array $params)
    {
        try {

            $headers = $this->getHeaders();

            $params['offer_id']     = $this->coniqOfferID;
            $params['location_id']  = $this->coniqLocationID;
            
            // if(!empty($params['amount'])){
            //     $params['amount']       = $this->getAmount($params['amount']);
            // }
            
            // Make the GET request with headers and query params
            $verify_transaction = Http::withHeaders($headers)->post($this->coniqApiUrl . '/verify-transaction', $params)->json();

            Log::channel('loyalty')->info('Successfully verify transaction API called.', [
                'response' => $verify_transaction,
                'params'=>$params
            ]);

            return $verify_transaction;
            
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('verify transaction retrieve API call failed.', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }



    /*
        To show offer amount according to price.
    */
    public function createTransaction(array $params)
    {
        try {

            $headers = $this->getHeaders();

            $params['offer_id']     = $this->coniqOfferID;
            $params['location_id']  = $this->coniqLocationID;
            
            // if(!empty($params['amount'])){
            //     $params['amount']       = $this->getAmount($params['amount']);
            // }
            
            // Make the GET request with headers and query params
            $create_transaction = Http::withHeaders($headers)->post($this->coniqApiUrl . '/create-transaction', $params)->json();

            Log::channel('loyalty')->info('Successfully create transaction API called.', [
                'response' => $create_transaction,
                'payload' => $params,
            ]);
            
            return $create_transaction;
            
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('create transaction API call failed.', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }

    /*
        For anonymous transaction
    */
    public function anonymousTransaction(array $params)
    {
        try {

            $headers = $this->getHeaders();

            $params['location_id']  = $this->coniqLocationID;
            
            // if(!empty($params['amount'])){
            //     $params['amount']       = $this->getAmount($params['amount']);
            // }
            
            // Make the GET request with headers and query params
            $anonymous_transaction = Http::withHeaders($headers)->post($this->coniqApiUrl . '/anonymous-transaction', $params)->json();

            Log::channel('loyalty')->info('Successfully anonymous transaction API called.', [
                'response' => $anonymous_transaction,
                'payload' => $params
            ]);
            
            return $anonymous_transaction;
            
        } catch (\Exception $e) {
            Log::channel('loyalty')->info('anonymous transaction API call failed.', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }
     

    public function validDiscount($request)
    {
        try{

            $response = [
                'status'    => 'error',
                'message'   => '',
            ];
            
            if(empty($request->extra_params['rule']['rule_id'])){
                $response['message'] = 'Rule id is not available.';
            }elseif(empty($request->extra_params['amount'])){
                $response['message'] = 'Amount is not available.';
            }elseif(empty($request->extra_params['customer_email'])){
                $response['message'] = 'Customer email is not available.';
            }

            $customer_email = $request->extra_params['customer_email'];
            $rule_id        = $request->extra_params['rule']['rule_id'];

            if(!empty($response['message'])){
                throw new \Exception($response['message']);
            }
        
            $barcode_data = $this->getBarcode($customer_email);

            if(empty($barcode_data)){
                throw new \Exception('User '.$customer_email.' unable to get barcode in validDiscount function.');
            }

            $params['barcode']  = $barcode_data[0]['barcode_number'];
            $params['amount']   = $this->getAmount($request->extra_params['amount']);
            $params['rule']     = $rule_id;
            $params['type']     = 'spend';

            $verify_response    = $this->verifyTransaction($params);

            if(isset($verify_response['success']) && $verify_response['success']){
                $response = [
                    'status' => 'success'
                ];
            }else{
                $error_msg = '';
                if(!empty($verify_response['error']) && is_array($verify_response['error'])){
                    $error_msg = implode(',',$verify_response['error']);
                }
                $response['message'] = 'Transaction verification is failed. - Error: '.$error_msg;
            }

        }catch (\Exception $e) {
            Log::channel('loyalty')->info('Failed valid discount.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    public function getCustomerDataByEmail($email){
        try {

            $headers = $this->getHeaders();

            $params['email']  = $email;
            
            // Make the GET request with headers and query params
            $response = Http::withHeaders($headers)->get($this->coniqApiUrl . '/customer', $params)->json();

            Log::channel('singup')->info('Customer Data retrieve successfully.', [
                'response' => $response,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::channel('singup')->info('Customer Data Failed.', [
                'error' => $e->getMessage(),
                'payload' => $params,
            ]);
        }
    }
}
