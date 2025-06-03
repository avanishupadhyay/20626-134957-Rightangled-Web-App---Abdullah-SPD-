<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyInstallerController extends Controller
{
    // Shopify app credentials
    protected $clientId;
    protected $clientSecret;

    // Scopes your app needs
    protected $scopes;

    // Redirect URI as defined in the Shopify Partner Dashboard
    protected $redirectUri;

    protected $storeDomain;


    public function __construct()
    {

        $this->clientId     = config('Shopify.app_client_id');
        $this->clientSecret = config('Shopify.app_client_secret');
        $this->storeDomain  = config('Shopify.api_host');
        $this->redirectUri  = config('app.url') . '/shopify/callback';
        $this->scopes       = 'write_draft_orders,read_draft_orders,read_orders,write_orders,read_products';
    }

    /**
     * Redirect the user to Shopify's authorization URL.
     */
    public function redirectToShopify(Request $request)
    {
        
        //$shop = $request->input('shop'); // Shopify store domain (e.g., example.myshopify.com)
        $shop = $this->storeDomain; // Shopify store domain (e.g., example.myshopify.com)

        // Validate the shop domain
        if (!$shop || !preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop)) {
            return response()->json(['error' => 'Invalid shop domain'], 400);
        }

        // Generate a random state parameter for CSRF protection
        $state = bin2hex(random_bytes(16));
        session(['oauth_state' => $state]);

        // Construct the authorization URL
        $authUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id'     => $this->clientId,
            'scope'         => $this->scopes,
            'redirect_uri'  => $this->redirectUri,
            'state'         => $state,
        ]);

        // Redirect to Shopify's OAuth page
        return redirect($authUrl);
    }

    /**
     * Handle the callback from Shopify and generate an access token.
     */
    public function handleShopifyCallback(Request $request)
    {
        $shop   = $request->input('shop'); // Shopify store domain
        $code   = $request->input('code'); // Authorization code
        $state  = $request->input('state'); // CSRF state

        // Validate the state parameter
        if ($state !== session('oauth_state')) {
            return response()->json(['error' => 'Invalid state parameter'], 400);
        }

        // Validate the shop domain
        if (!$shop || !preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $shop)) {
            return response()->json(['error' => 'Invalid shop domain'], 400);
        }

        // Exchange the authorization code for an access token
        $response = Http::post("https://{$shop}/admin/oauth/access_token", [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
        ]);

        if ($response->successful()) {
            $accessToken = $response->json()['access_token'];

            // Save the access token securely in your database

            if(!empty($accessToken)){
                $configuration = \App\Models\Configuration::where('name', 'Shopify.access_token')->first();
            
                $configuration->value = $accessToken;
                $configuration->save();

            }

             return response()->json([
                'message' => 'Access token generated successfully',
                'access_token' => $accessToken,
            ]);
        }

        return response()->json(['error' => 'Failed to get access token'], 400);
    }
}
