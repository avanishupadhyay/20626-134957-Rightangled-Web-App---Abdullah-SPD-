<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;
use App\Models\Store;
use App\Models\OrderAction;
use App\Models\Prescriber;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

if (!function_exists('pr')) {

	function pr($data)
	{
		echo '<pre>';
		print_r($data);
		echo '</pre>';
	}
}


if (!function_exists('getConfigurationMenu')) {

	function getConfigurationMenu()
	{
		$configuration = new \App\Models\Configuration();
		return $configuration->getprefix();
	}
}


if (!function_exists('getTotalSubscribeUser')) {

	function getTotalSubscribeUser()
	{
		$userLogObj = new \App\Models\UserLog();
		return $userLogObj->count();
	}
}

if (!function_exists('getTotalUserOrder')) {

	function getTotalUserOrder()
	{
		$orderObj = new \App\Models\Order();
		return $orderObj->count();
	}
}

if (!function_exists('getTotalUserLoyality')) {

	function getTotalUserLoyality()
	{
		$loyalityObj = new \App\Models\Loyality();
		return $loyalityObj->count();
	}
}


if (!function_exists('getRecentSubscribers')) {

	function getRecentSubscribers()
	{
		$userLogObj = 	new \App\Models\UserLog();
		$userLogs	=	$userLogObj->orderBy('id', 'desc')->limit(10)->get();

		return $userLogs;
	}
}


if (!function_exists('getDraftOrderMutation')) {

	function getDraftOrderMutation()
	{
		return 'mutation DraftOrderCreate($input: DraftOrderInput!) {
			draftOrderCreate(input: $input) {
				draftOrder {
				id
				note2
				email
				taxesIncluded
				currencyCode
				invoiceSentAt
				createdAt
				updatedAt
				taxExempt
				completedAt
				name
				status
				lineItems(first: 10) {
					edges {
					node {
						id
						variant {
						id
						title
						}
						product {
						id
						}
						name
						sku
						vendor
						quantity
						requiresShipping
						taxable
						isGiftCard
						fulfillmentService {
						type
						}
						weight {
						unit
						value
						}
						taxLines {
						title
						source
						rate
						ratePercentage
						priceSet {
							presentmentMoney {
							amount
							currencyCode
							}
							shopMoney {
							amount
							currencyCode
							}
						}
						}
						appliedDiscount {
						title
						value
						valueType
						}
						name
						custom
						id
					}
					}
				}
				shippingAddress {
					firstName
					address1
					phone
					city
					zip
					province
					country
					lastName
					address2
					company
					latitude
					longitude
					name
					country
					countryCodeV2
					provinceCode
				}
				billingAddress {
					firstName
					address1
					phone
					city
					zip
					province
					country
					lastName
					address2
					company
					latitude
					longitude
					name
					country
					countryCodeV2
					provinceCode
				}
				invoiceUrl
				appliedDiscount {
					title
					value
					valueType
				}
				order {
					id
					customAttributes {
					key
					value
					}
				}
				shippingLine {
					id
					title
					carrierIdentifier
					custom
					code
					deliveryCategory
					source
					discountedPriceSet {
					presentmentMoney {
						amount
						currencyCode
					}
					shopMoney {
						amount
						currencyCode
					}
					}
				}
				taxLines {
					channelLiable
					priceSet {
					presentmentMoney {
						amount
						currencyCode
					}
					shopMoney {
						amount
						currencyCode
					}
					}
					rate
					ratePercentage
					source
					title
				}
				tags
				customer {
					id
					email
					smsMarketingConsent {
					consentCollectedFrom
					consentUpdatedAt
					marketingOptInLevel
					marketingState
					}
					emailMarketingConsent {
					consentUpdatedAt
					marketingOptInLevel
					marketingState
					}
					createdAt
					updatedAt
					firstName
					lastName
					state
					amountSpent {
					amount
					currencyCode
					}
					lastOrder {
					id
					name
					currencyCode
					}
					note
					verifiedEmail
					multipassIdentifier
					taxExempt
					tags
					phone
					taxExemptions
					defaultAddress {
					id
					firstName
					lastName
					company
					address1
					address2
					city
					province
					country
					zip
					phone
					name
					provinceCode
					countryCodeV2
					}
				}
				}
				userErrors {
				field
				message
				}
			}
			}';
	}
}

if (!function_exists('getUpdateDraftOrderMutation')) {
	function getUpdateDraftOrderMutation()
	{
		return 'mutation updateDraftOrderDiscount($id: ID!, $input: DraftOrderInput!) {
					draftOrderUpdate(id: $id, input: $input) {
					draftOrder {
						id
						appliedDiscount {
							value
							valueType
							title
						}
					}
					userErrors {
						message
						field
					}
					}
				}';
	}
}


if (!function_exists('getLastIntegerFromGid')) {
	function getLastIntegerFromGid($gid)
	{
		/* gid example : gid://shopify/Customer/8474572914881 */
		if (preg_match('/(\d+)$/', $gid, $matches)) {
			return $matches[1]; /* Return the matched digits */
		}

		return null;
	}
}

if (!function_exists('isLoyaltyUserExists')) {
	function isLoyaltyUserExists($email)
	{
		$userExists = \App\Models\UserLog::where('email', $email)->where('marketing_agreement', 1)->exists();

		if ($userExists) {
			return true;
		}

		return false;
	}
}


if (!function_exists('isOrderExists')) {
	function isOrderExists($order_number)
	{
		$orderExists = \App\Models\Order::where('order_number', $order_number)->exists();

		if ($orderExists) {
			return true;
		}

		return false;
	}
}

function getCountryISDByCode($country_code)
{

	$isd_codes = [
		'AF' => '93',
		'AL' => '355',
		'DZ' => '213',
		'AS' => '1-684',
		'AD' => '376',
		'AO' => '244',
		'AI' => '1-264',
		'AG' => '1-268',
		'AR' => '54',
		'AM' => '374',
		'AW' => '297',
		'AU' => '61',
		'AT' => '43',
		'AZ' => '994',
		'BS' => '1-242',
		'BH' => '973',
		'BD' => '880',
		'BB' => '1-246',
		'BY' => '375',
		'BE' => '32',
		'BZ' => '501',
		'BJ' => '229',
		'BM' => '1-441',
		'BT' => '975',
		'BO' => '591',
		'BA' => '387',
		'BW' => '267',
		'BR' => '55',
		'BN' => '673',
		'BG' => '359',
		'BF' => '226',
		'BI' => '257',
		'KH' => '855',
		'CM' => '237',
		'CA' => '1',
		'CV' => '238',
		'CF' => '236',
		'TD' => '235',
		'CL' => '56',
		'CN' => '86',
		'CO' => '57',
		'KM' => '269',
		'CG' => '242',
		'CD' => '243',
		'CR' => '506',
		'CI' => '225',
		'HR' => '385',
		'CU' => '53',
		'CY' => '357',
		'CZ' => '420',
		'DK' => '45',
		'DJ' => '253',
		'DM' => '1-767',
		'DO' => '1-809',
		'EC' => '593',
		'EG' => '20',
		'SV' => '503',
		'GQ' => '240',
		'ER' => '291',
		'EE' => '372',
		'ET' => '251',
		'FJ' => '679',
		'FI' => '358',
		'FR' => '33',
		'GA' => '241',
		'GM' => '220',
		'GE' => '995',
		'DE' => '49',
		'GH' => '233',
		'GR' => '30',
		'GT' => '502',
		'GN' => '224',
		'GW' => '245',
		'GY' => '592',
		'HT' => '509',
		'HN' => '504',
		'HU' => '36',
		'IS' => '354',
		'IN' => '91',
		'ID' => '62',
		'IR' => '98',
		'IQ' => '964',
		'IE' => '353',
		'IL' => '972',
		'IT' => '39',
		'JM' => '1-876',
		'JP' => '81',
		'JO' => '962',
		'KZ' => '7',
		'KE' => '254',
		'KI' => '686',
		'KP' => '850',
		'KR' => '82',
		'KW' => '965',
		'KG' => '996',
		'LA' => '856',
		'LV' => '371',
		'LB' => '961',
		'LS' => '266',
		'LR' => '231',
		'LY' => '218',
		'LI' => '423',
		'LT' => '370',
		'LU' => '352',
		'MG' => '261',
		'MW' => '265',
		'MY' => '60',
		'MV' => '960',
		'ML' => '223',
		'MT' => '356',
		'MH' => '692',
		'MR' => '222',
		'MU' => '230',
		'MX' => '52',
		'FM' => '691',
		'MD' => '373',
		'MC' => '377',
		'MN' => '976',
		'ME' => '382',
		'MA' => '212',
		'MZ' => '258',
		'MM' => '95',
		'NA' => '264',
		'NR' => '674',
		'NP' => '977',
		'NL' => '31',
		'NZ' => '64',
		'NI' => '505',
		'NE' => '227',
		'NG' => '234',
		'NO' => '47',
		'OM' => '968',
		'PK' => '92',
		'PW' => '680',
		'PA' => '507',
		'PG' => '675',
		'PY' => '595',
		'PE' => '51',
		'PH' => '63',
		'PL' => '48',
		'PT' => '351',
		'QA' => '974',
		'RO' => '40',
		'RU' => '7',
		'RW' => '250',
		'SA' => '966',
		'SN' => '221',
		'RS' => '381',
		'SC' => '248',
		'SL' => '232',
		'SG' => '65',
		'SK' => '421',
		'SI' => '386',
		'SB' => '677',
		'SO' => '252',
		'ZA' => '27',
		'ES' => '34',
		'LK' => '94',
		'SD' => '249',
		'SR' => '597',
		'SZ' => '268',
		'SE' => '46',
		'CH' => '41',
		'SY' => '963',
		'TJ' => '992',
		'TZ' => '255',
		'TH' => '66',
		'TG' => '228',
		'TO' => '676',
		'TT' => '1-868',
		'TN' => '216',
		'TR' => '90',
		'TM' => '993',
		'UG' => '256',
		'UA' => '380',
		'AE' => '971',
		'GB' => '44',
		'US' => '1',
		'UY' => '598',
		'UZ' => '998',
		'VU' => '678',
		'VA' => '39',
		'VE' => '58',
		'VN' => '84',
		'YE' => '967',
		'ZM' => '260',
		'ZW' => '263'
	];

	return !empty($isd_codes[$country_code]) ? $isd_codes[$country_code] : '44';
}


// function getProductMetafield($productId)
// {
// 	// $shopDomain = config('shopify.domain');
// 	// $accessToken = config('shopify.access_token');
// 	$shopDomain = 'rightangled-store.myshopify.com'; // e.g., yourstore.myshopify.com
// 	$accessToken = '';

// 	$url = "https://{$shopDomain}/admin/api/2024-01/products/{$productId}/metafields.json";

// 	$response = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 		'Content-Type' => 'application/json',
// 	])->get($url);

// 	if ($response->successful()) {
// 		foreach ($response['metafields'] as $field) {
// 			if ($field['key'] === $namespaceKey) {
// 				return $field['value'];
// 			}
// 		}
// 	}

// 	return 'N/A';
// }
// helpers.php

function getShopifyCredentialsByOrderId($orderId)
{
	$order = \App\Models\Order::with('store')->where('order_number', $orderId)->first();

	if (!$order || !$order->store) {
		throw new \Exception('Store not found for the given order ID.');
	}

	return [
		'shopDomain'   => $order->store->domain, // e.g., "your-shop.myshopify.com"
		'accessToken'  => $order->store->app_admin_access_token,
	];

	// Fallback:
	// return [
	//     'shopDomain'   => env('SHOP_DOMAIN'),
	//     'accessToken'  => env('ACCESS_TOKEN'),
	// ];
}


function getProductMetafield($productId, $orderId)
{
	// $shopDomain = env('SHOP_DOMAIN');
	// $accessToken = env('ACCESS_TOKEN');
	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

	$response = Http::withHeaders([
		'X-Shopify-Access-Token' => $accessToken,
	])->get("{$shopDomain}/admin/api/2024-10/products/{$productId}/metafields.json");

	if ($response->successful()) {
		$metafields = $response->json('metafields');

		return collect($metafields)->firstWhere('key', 'direction_of_use_single_line')['value'] ?? null;
	}

	return null;
}



function getOrderMetafields($orderId)
{

	// $shopDomain = env('SHOP_DOMAIN');
	// $accessToken = env('ACCESS_TOKEN');
	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));
	// ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);


	$apiVersion = '2024-10';

	$response = Http::withHeaders([
		'X-Shopify-Access-Token' => $accessToken,
	])->get("{$shopDomain}/admin/api/{$apiVersion}/orders/{$orderId}/metafields.json");

	if ($response->successful()) {
		$metafields = collect($response->json('metafields'));
		// dd($metafields);

		return [
			'prescriber_s_name' => $metafields->firstWhere('key', 'prescriber_s_name')['value'] ?? null,
			'gphc_number_' => $metafields->firstWhere('key', 'gphc_number_')['value'] ?? null,
			'patient_s_dob' => $metafields->firstWhere('key', 'patient_s_dob')['value'] ?? null,
			'approval' => $metafields->firstWhere('key', 'approval')['value'] ?? null,
			'prescriber_s_signature' => $metafields->firstWhere('key', 'prescriber_s_signature')['value'] ?? null, // optional image URL
			'on_hold_reason' => $metafields->firstWhere('key', 'on_hold_reason')['value'] ?? null, // optional image URL
			'prescriber_pdf' => $metafields->firstWhere('key', 'prescriber_pdf')['value'] ?? null, // optional image URL
			'checker_name' => $metafields->firstWhere('key', 'checker_name')['value'] ?? null, // optional image URL
			'checker_approval' => $metafields->firstWhere('key', 'checker_approval')['value'] ?? null, // optional image URL
			'checker_notes' => $metafields->firstWhere('key', 'checker_notes')['value'] ?? null, // optional image URL

		];
	}

	return [];
}

// function buildCommonMetafields(Request $request, string $decisionStatus, $orderId, $pdfUrl = null): array
// {
// 	$user = auth()->user();
// 	$prescriberData = $user->prescriber;

// 	$resourceGid = 'gid://shopify/Order/'.$orderId;
// 	if(empty($prescriberData->signature_image)){
// 		$imageUrl = asset('admin/signature-images/signature.png');
// 	}else{
// 		$filePath = "signature-images/{$prescriberData->signature_image}";
// 		$imageUrl = rtrim(config('app.url'), '/') . '/' . ltrim(Storage::url($filePath), '/');
// 		// $imageUrl = asset('admin/signature-images/' . $prescriberData->signature_image);
// 	}
// 	$file_id = uploadImageAndSaveMetafield($imageUrl);

// 	$metafields = [
// 		[
// 			'ownerId' => $resourceGid,
// 			'namespace' => 'custom',
// 			'key' => 'prescriber_id',
// 			'type' => 'number_integer',
// 			'value' => $user->id,
// 		],
// 		[
// 			'namespace' => 'custom',
// 			'key' => 'prescriber_s_name',
// 			'type' => 'single_line_text_field',
// 			'value' => $user->name ?? 'admin_user',
// 		],
// 		[
// 			'namespace' => 'custom',
// 			'key' => 'gphc_number_',
// 			'type' => 'single_line_text_field',
// 			'value' => $prescriberData->gphc_number ?? 'marked_by admin',
// 		],
// 		// [
// 		// 	'namespace' => 'custom',
// 		// 	'key' => 'prescriber_s_signature',
// 		// 	'type' => 'single_line_text_field',
// 		// 	'value' => $prescriberData->signature_image ?? 'Signed by ' . $user->name,
// 		// ],
// 		[
// 			'ownerId' => $resourceGid,
// 			'namespace' => 'custom',
// 			'key' => 'prescriber_s_signatures',
// 			'type' => 'file_reference',
// 			'value' => $file_id ?? '',
// 		],
// 		[
// 			'namespace' => 'custom',
// 			'key' => 'decision_status',
// 			'type' => 'single_line_text_field',
// 			'value' => $decisionStatus,
// 		],
// 		[
// 			'namespace' => 'custom',
// 			'key' => 'decision_timestamp',
// 			'type' => 'date_time',
// 			'value' => now()->toIso8601String(),
// 		],

// 	];

// 	if ($decisionStatus === 'approved') {
// 		$metafields[] = [
// 			'namespace' => 'custom',
// 			'key' => 'clinical_reasoning',
// 			'type' => 'multi_line_text_field',
// 			'value' => $request->clinical_reasoning,
// 		];
// 		$metafields[] = [
// 			'namespace' => 'custom',
// 			'key' => 'patient_s_dob',
// 			'type' => 'date',
// 			'value' => $request->patient_s_dob,
// 		];
// 		$metafields[] = [
// 			'namespace' => 'custom',
// 			'key' => 'approval',
// 			'type' => 'boolean',
// 			'value' => true,
// 		];
// 		$metafields[] = [
// 			'namespace' => 'custom',
// 			'key' => 'prescriber_pdf',
// 			'type' => 'url',
// 			'value' => $pdfUrl,
// 		];
// 	} elseif ($decisionStatus === 'rejected') {
// 		$metafields[] = [
// 			'namespace' => 'custom',
// 			'key' => 'rejection_reason',
// 			'type' => 'multi_line_text_field',
// 			'value' => $request->rejection_reason,
// 		];
// 	} elseif ($decisionStatus === 'on_hold') {
// 		$metafields[] = [
// 			'namespace' => 'custom',
// 			'key' => 'on_hold_reason',
// 			'type' => 'multi_line_text_field',
// 			'value' => $request->on_hold_reason,
// 		];
// 	}

// 	return $metafields;
// }

function buildCommonMetafields(Request $request, string $decisionStatus, $orderId, $pdfUrl = null): array
{
	$user = auth()->user();
	$prescriberData = $user->prescriber;

	$resourceGid = 'gid://shopify/Order/' . $orderId;
	if (empty($prescriberData->signature_image)) {
		$imageUrl = rtrim(config('app.url'), '/') . '/' . ltrim(Storage::url('signature-images/signature.png'), '/');
		// asset('admin/signature-images/signature.png');
	} else {
		$filePath = "signature-images/{$prescriberData->signature_image}";
		$imageUrl = rtrim(config('app.url'), '/') . '/' . ltrim(Storage::url($filePath), '/');
		// $imageUrl = asset('admin/signature-images/' . $prescriberData->signature_image);
	}
	
	$file_id = uploadImageAndSaveMetafield($imageUrl, $orderId);
	
	$metafields = [
		[
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'prescriber_id',
			'type' => 'number_integer',
			'value' => (string) $user->id ?? '',
		],
		[
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'prescriber_s_name',
			'type' => 'single_line_text_field',
			'value' => $user->name ?? 'admin_user',
		],
		[
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'gphc_number_',
			'type' => 'single_line_text_field',
			'value' => $prescriberData->gphc_number ?? 'marked_by admin',
		],
		// [
		// 	'namespace' => 'custom',
		// 	'key' => 'prescriber_s_signature',
		// 	'type' => 'single_line_text_field',
		// 	'value' => $prescriberData->signature_image ?? 'Signed by ' . $user->name,
		// ],
		[
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'prescriber_s_signatures',
			'type' => 'file_reference',
			'value' => $file_id ?? '',
		],
		[
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'decision_status',
			'type' => 'single_line_text_field',
			'value' => $decisionStatus ?? '',
		],
		[
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'decision_timestamp',
			'type' => 'date_time',
			// 'value' => now()->toIso8601String(),
			'value' => now()->toAtomString(),
		],

	];

	if ($decisionStatus === 'approved') {
		$metafields[] = [
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'clinical_reasoning',
			'type' => 'multi_line_text_field',
			'value' => $request->clinical_reasoning ?? '',
		];
		$metafields[] = [
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'patient_s_dob',
			'type' => 'date',
			// 'value' => $request->patient_s_dob ?? '',
			'value' => date('Y-m-d', strtotime($request->patient_s_dob ?? now())),
		];
		$metafields[] = [
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'approval',
			'type' => 'boolean',
			'value' => 'true',
		];
		$metafields[] = [
			'ownerId' => $resourceGid,
			'namespace' => 'custom',
			'key' => 'prescriber_pdf',
			'type' => 'url',
			'value' => $pdfUrl ?? '',
		];
	} elseif ($decisionStatus === 'rejected') {
		$metafields[] = [
			'namespace' => 'custom',
			'key' => 'rejection_reason',
			'type' => 'multi_line_text_field',
			'value' => $request->rejection_reason ?? '',
		];
	} elseif ($decisionStatus === 'on_hold') {
		$metafields[] = [
			'namespace' => 'custom',
			'key' => 'on_hold_reason',
			'type' => 'multi_line_text_field',
			'value' => $request->on_hold_reason ?? '',
		];
	}

	return $metafields;
}

function metaiFieldAdmin(Request $request, string $decisionStatus)
{
	$metafields = [
		[
			'namespace' => 'custom',
			'key' => 'admin_notes',
			'type' => 'multi_line_text_field',
			'value' => $decisionStatus .' - '. $request->clinical_reasoning,
		],
	];
	
	return $metafields;
}

function buildCommonMetafieldsChecker(Request $request, string $decisionStatus): array
{
	$user = auth()->user();

	$metafields = [
		[
			'namespace' => 'custom',
			'key' => 'checker_id',
			'type' => 'number_integer',
			'value' => $user->id,
		],
		[
			'namespace' => 'custom',
			'key' => 'checker_name',
			'type' => 'single_line_text_field',
			'value' => $user->name ?? 'admin_user',
		],

		[
			'namespace' => 'custom',
			'key' => 'checker_decision_status',
			'type' => 'single_line_text_field',
			'value' => $decisionStatus,
		],
		[
			'namespace' => 'custom',
			'key' => 'decision_timestamp',
			'type' => 'date_time',
			'value' => now()->toIso8601String(),
		],

	];

	if ($decisionStatus === 'approved') {
		$metafields[] = [
			'namespace' => 'custom',
			'key' => 'checker_notes',
			'type' => 'multi_line_text_field',
			'value' => 'Order Approved: ' . $request->clinical_reasoning,
		];
		$metafields[] = [
			'namespace' => 'custom',
			'key' => 'checker_approval',
			'type' => 'boolean',
			'value' => true,
		];
	} elseif ($decisionStatus === 'rejected') {
		$metafields[] = [
			'namespace' => 'custom',
			'key' => 'checker_notes',
			'type' => 'multi_line_text_field',
			'value' =>  'Order Rejected: ' . $request->rejection_reason,
		];
	} elseif ($decisionStatus === 'on_hold') {
		$metafields[] = [
			'namespace' => 'custom',
			'key' => 'checker_notes',
			'type' => 'multi_line_text_field',
			'value' => 'Order On Hold: ' . $request->on_hold_reason,
		];
	}

	return $metafields;
}

// function markFulfillmentOnHold($orderId, $reason)
// {
// 	$shopDomain = env('SHOP_DOMAIN');
// 	$accessToken = env('ACCESS_TOKEN');
// 	// ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);
// 	// Step 1: Get the order to fetch fulfillment_order ID
// 	$response = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 	])->get("https://{$shopDomain}/admin/api/2023-10/orders/{$orderId}/fulfillment_orders.json");

// 	$fulfillmentOrders = $response->json('fulfillment_orders');
// 	if (empty($fulfillmentOrders)) {
// 		return response()->json(['error' => 'No fulfillment orders found.'], 404);
// 	}
// 	$fulfillmentOrderId = $fulfillmentOrders[0]['id'];
// 	// Step 2: Create fulfillment hold (mark as on-hold)
// 	$holdResponse = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 		'Content-Type' => 'application/json',
// 	])->post("https://{$shopDomain}/admin/api/2023-10/fulfillment_orders/{$fulfillmentOrderId}/hold.json", [
// 		'fulfillment_hold' => [
// 			'reason' => 'other', // ✅ valid reason
// 			'reason_notes' => $reason ?? 'Order placed on hold during review.',
// 		],
// 	]);
// 	if ($holdResponse->failed()) {
// 		return response()->json([
// 			'error' => 'Failed to put fulfillment on hold',
// 			'details' => $holdResponse->json()
// 		], 500);
// 	}

// 	return true;
// }


function markFulfillmentOnHold($orderId, $reason)
{
	// $shopDomain = env('SHOP_DOMAIN');
	// $accessToken = env('ACCESS_TOKEN');
	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

	// ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);

	// Step 1: Get all fulfillment orders
	$response = Http::withHeaders([
		'X-Shopify-Access-Token' => $accessToken,
	])->get("{$shopDomain}/admin/api/2023-10/orders/{$orderId}/fulfillment_orders.json");

	$fulfillmentOrders = $response->json('fulfillment_orders');
	if (empty($fulfillmentOrders)) {
		return response()->json(['error' => 'No fulfillment orders found.'], 404);
	}

	// Step 2: Loop through each fulfillment order and put it on hold
	foreach ($fulfillmentOrders as $fulfillmentOrder) {
		$fulfillmentOrderId = $fulfillmentOrder['id'];

		$holdResponse = Http::withHeaders([
			'X-Shopify-Access-Token' => $accessToken,
			'Content-Type' => 'application/json',
		])->post("{$shopDomain}/admin/api/2023-10/fulfillment_orders/{$fulfillmentOrderId}/hold.json", [
			'fulfillment_hold' => [
				'reason' => 'other',
				'reason_notes' => $reason ?? 'Order placed on hold during review.',
			],
		]);

		if ($holdResponse->failed()) {
			return response()->json([
				'error' => 'Failed to put fulfillment on hold for fulfillment order ' . $fulfillmentOrderId,
				'details' => $holdResponse->json()
			], 500);
		}
	}

	return true;
}


function cancelOrder($orderId, $reason)
{
	// $shopDomain = env('SHOP_DOMAIN');
	// $accessToken = env('ACCESS_TOKEN');
	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

	// ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);


	$response = Http::withHeaders([
		'X-Shopify-Access-Token' => $accessToken,
		'Content-Type' => 'application/json',
	])->post("{$shopDomain}/admin/api/2023-10/orders/{$orderId}/cancel.json", [
		'email' => true,
		'reason' => $reason, // or 'other', 'fraud', 'inventory'
		// 'restock' => true,
		'note' => $reason ?? 'Order rejected by prescriber.',

	]);

	if ($response->failed()) {
		throw new \Exception('Order cancellation failed: ' . json_encode($response->json()));
	}

	return true;
}


function getOrderDecisionStatus($orderId)
{
	$order = Order::where('id', $orderId)->first();

	if (!$order) return null;

	// Get latest OrderAction by order_number (used as order_id in OrderAction)
	$latestAction = OrderAction::where('order_id', $order->order_number)
		->latest('decision_timestamp')
		->first();

	$decisionStatus = optional($latestAction)->decision_status;

	// ✅ Decode order_data if it's a string
	$orderData = is_array($order->order_data)
		? $order->order_data
		: json_decode($order->order_data, true);

	$cancelledAt = null;

	if (
		isset($orderData['cancelled_at']) &&
		$orderData['cancelled_at'] !== null &&
		$orderData['cancelled_at'] !== 'null'
	) {
		$cancelledAt = $orderData['cancelled_at'];
	}

	return [
		'latest_decision_status' => $decisionStatus,
		'fulfillment_status'     => $order->fulfillment_status,
		'is_cancelled'           => $cancelledAt !== null,
		'cancelled_at'           => $cancelledAt,
	];
}


// function releaseFulfillmentHold($orderId, $reason)
// {
// 	// $shopDomain = env('SHOP_DOMAIN');
// 	// $accessToken = env('ACCESS_TOKEN');
// 	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

// 	// ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = getShopifyCredentialsByOrderId($orderId);

// 	// Step 1: Get fulfillment orders for the order
// 	$response = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 	])->get("{$shopDomain}/admin/api/2023-10/orders/{$orderId}/fulfillment_orders.json");

// 	if ($response->failed()) {
// 		return response()->json([
// 			'error' => 'Failed to fetch fulfillment orders',
// 			'details' => $response->json()
// 		], 500);
// 	}

// 	$fulfillmentOrders = $response->json('fulfillment_orders');

// 	if (empty($fulfillmentOrders)) {
// 		return response()->json(['error' => 'No fulfillment orders found.'], 404);
// 	}

// 	$fulfillmentOrderId = $fulfillmentOrders[0]['id'];

// 	// Step 2: Release hold
// 	$releaseResponse = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 		'Content-Type' => 'application/json',
// 	])->post("{$shopDomain}/admin/api/2023-10/fulfillment_orders/{$fulfillmentOrderId}/release_hold.json");

// 	if ($releaseResponse->failed()) {
// 		return response()->json([
// 			'error' => 'Failed to release fulfillment hold',
// 			'details' => $releaseResponse->json()
// 		], 500);
// 	}

// 	return true;
// }

function releaseFulfillmentHold($orderId, $reason)
{
    [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

    // Step 1: Get all fulfillment orders for this order
    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $accessToken,
    ])->get("{$shopDomain}/admin/api/2023-10/orders/{$orderId}/fulfillment_orders.json");

    if ($response->failed()) {
        return response()->json([
            'error' => 'Failed to fetch fulfillment orders',
            'details' => $response->json(),
        ], 500);
    }

    $fulfillmentOrders = $response->json('fulfillment_orders');

    if (empty($fulfillmentOrders)) {
        return response()->json(['error' => 'No fulfillment orders found.'], 404);
    }

    $errors = [];

    // Step 2: Loop through each fulfillment order and release hold
    foreach ($fulfillmentOrders as $fulfillmentOrder) {
        $fulfillmentOrderId = $fulfillmentOrder['id'];

        $releaseResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$shopDomain}/admin/api/2023-10/fulfillment_orders/{$fulfillmentOrderId}/release_hold.json");

        if ($releaseResponse->failed()) {
            $errors[] = [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'details' => $releaseResponse->json()
            ];
        }
    }

    if (!empty($errors)) {
        return response()->json([
            'error' => 'Some fulfillment holds could not be released.',
            'details' => $errors,
        ], 500);
    }

    return true;
}



if (!function_exists('getPrescriptionData')) {
	function getPrescriptionData($order_id)
	{
		$prescriber_data = [];
		$prescriber_data = \App\Models\Prescriptions::select('prescriber_id', 'decision_status', 'clinical_reasoning')->where('order_id', $order_id)->first();
		return $prescriber_data;
	}
}

// if (!function_exists('getAuditData')) {
// 	function getAuditData($order_id)
// 	{
// 		$audit_data = [];
// 		$audit_data = \App\Models\AuditLog::select('action')->where('order_id', $order_id)->OrderBy('id','DESC')->first();
// 		return $audit_data;
// 	}
// }

if (!function_exists('getOrderData')) {
	function getOrderData($order_id)
	{

		return \App\Models\Order::where('order_number', $order_id)->first();
	}
}


// $imageUrl = 'https://rightangled.24livehost.com/storage/configuration-images/logo-1748949654.png'; // Must be public
// $orderIdGid = 'gid://shopify/Order/5794153988154';
// $response = $this->uploadImageAndSaveMetafield($imageUrl, $orderIdGid);
// dd($response);

function uploadImageAndSaveMetafield($publicImageUrl, $orderId)
{

	// $shop = env('SHOP_DOMAIN'); // e.g., your-store.myshopify.com
	// $token = env('ACCESS_TOKEN');
	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

	$user = auth()->user();

	$store_id = getStoreId($orderId);
	$prescriber = Prescriber::where('user_id', $user->id)->first();

	if (!empty($prescriber->file_gid)) {
		$fileGidArray = json_decode($prescriber->file_gid, true);

		if (isset($fileGidArray[$store_id])) {
			return $fileGidArray[$store_id];
		}
	}
	// Step 1: Upload image to Shopify Files via GraphQL
	$uploadQuery = <<<'GRAPHQL'
            mutation fileCreate($files: [FileCreateInput!]!) {
            fileCreate(files: $files) {
                files {
                id
                alt
                createdAt
                ... on MediaImage {
                    image {
                    url
                    }
                }
                }
                userErrors {
                field
                message
                }
            }
            }
            GRAPHQL;
	$uploadResponse = Http::withHeaders([
		'X-Shopify-Access-Token' => $accessToken,
		'Content-Type' => 'application/json',
	])->post("{$shopDomain}/admin/api/2025-04/graphql.json", [
		'query' => $uploadQuery,
		'variables' => [
			'files' => [
				[
					'alt' => 'Uploaded image',
					'contentType' => 'IMAGE',
					'originalSource' => $publicImageUrl,
				]
			]
		],
	])->json();

	$fileData = $uploadResponse['data']['fileCreate']['files'][0] ?? null;

	if (!$fileData || !isset($fileData['id'])) {
		return [
			'status' => 'error',
			'message' => 'Image upload failed',
			'response' => $uploadResponse,
		];
	}

	$store_id = getStoreId($orderId);
	$prescriber = Prescriber::where('user_id', $user->id)->first();

	if ($prescriber) {
		$fileGidArray = [];

		// Step 1: Decode existing JSON
		if (!empty($prescriber->file_gid)) {
			$fileGidArray = json_decode($prescriber->file_gid, true);
		}

		// Step 2: Update or append the store_id => file_id
		$fileGidArray[$store_id] = $fileData['id'];

		// Step 3: Save updated JSON back to database
		$prescriber->update([
			'file_gid' => json_encode($fileGidArray)
		]);
	}
	// $fileGid = $fileData['id'];
	return $fileData['id'] ?? '';
}

function getShopifyImageUrl($gid, $orderId)
{
	// $endpoint = 'https://' . env('SHOP_DOMAIN') . '/admin/api/2024-01/graphql.json';
	// $accessToken = env('ACCESS_TOKEN');
	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($orderId));

	$query = <<<GQL
		{
		node(id: "$gid") {
			... on MediaImage {
			image {
				url
			}
			}
		}
		}
		GQL;

	$response = Http::withHeaders([
		'X-Shopify-Access-Token' => $accessToken,
		'Content-Type' => 'application/json',
	])->post("{$shopDomain}/admin/api/2024-01/graphql.json", [
		'query' => $query
	]);

	$data = $response->json();

	return $data['data']['node']['image']['url'] ?? null;
}


// function bulkAddShopifyTags(array $orderGIDs, string $tag,$shopifyOrderId)
// {
// 	// $shop = env('SHOP_DOMAIN'); // e.g., your-store.myshopify.com
// 	// $token = env('ACCESS_TOKEN');
// 	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));

// 	$mutationParts = [];
// 	$variableDeclarations = [];
// 	$variables = [
// 		'tag' => $tag,
// 	];

// 	foreach ($orderGIDs as $index => $gid) {
// 		$mutationName = "tagMutation$index";
// 		$variableName = "id$index";

// 		$variableDeclarations[] = "\$$variableName: ID!";
// 		$mutationParts[] = <<<GQL
//         $mutationName: tagsAdd(id: \$$variableName, tags: [\$tag]) {
//             node {
//                 id
//                 ... on Order {
//                     tags
//                 }
//             }
//             userErrors {
//                 field
//                 message
//             }
//         }
//         GQL;

// 		$variables[$variableName] = $gid;
// 	}

// 	// Use the helper functions *outside* the heredoc
// 	$allVariableDeclarations = implodeWithCommas($variableDeclarations);
// 	$allMutationParts = implodeWithNewLines($mutationParts);

// 	// Now embed the generated strings
// 	$query = <<<GQL
//     mutation BulkAddTags(\$tag: String!, $allVariableDeclarations) {
//         $allMutationParts
//     }
//     GQL;

// 	$response = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 		'Content-Type' => 'application/json',
// 	])->post("$shopDomain/admin/api/2024-01/graphql.json", [
// 		'query' => $query,
// 		'variables' => $variables,
// 	]);

// 	return $response->json();
// }



if (!function_exists('bulkAddShopifyTagsAndNotes')) {
	function bulkAddShopifyTagsAndNotes(array $ordersWithStoreIds, string $tag)
	{
		$credentialsCache = []; // Cache store credentials once per store

		// Group by store ID
		$groupedOrders = [];

		foreach ($ordersWithStoreIds as $order) {
			['gid' => $gid, 'store_id' => $storeId, 'shopify_order_id' => $shopifyOrderId] = $order;

			// Cache credentials per store
			if (!isset($credentialsCache[$storeId])) {
				[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));
				$credentialsCache[$storeId] = compact('shopDomain', 'accessToken');
			} else {
				$shopDomain = $credentialsCache[$storeId]['shopDomain'];
				$accessToken = $credentialsCache[$storeId]['accessToken'];
			}

			$key = md5($shopDomain); // Unique per store

			$groupedOrders[$key]['shopDomain'] = $shopDomain;
			$groupedOrders[$key]['accessToken'] = $accessToken;
			$groupedOrders[$key]['orders'][] = [
				'gid' => $gid,
				'shopify_order_id' => $shopifyOrderId,
			];
		}

		// Now perform bulk GraphQL mutation for each group
		foreach ($groupedOrders as $group) {
			$shopDomain = $group['shopDomain'];
			$accessToken = $group['accessToken'];
			$orders = $group['orders'];

			$mutationParts = [];
			$variableDeclarations = [];
			$variables = ['tag' => $tag];

			foreach ($orders as $index => $order) {
				$gid = $order['gid'];
				$variableName = "id$index";
				$noteVarName = "noteVar$index";

				$variableDeclarations[] = "\$$variableName: ID!";
				$variableDeclarations[] = "\$$noteVarName: String!";

				$variables[$variableName] = $gid;
				$variables[$noteVarName] = "Order '{$tag}'by " . (auth()->user()->name ?? 'System') . ' on ' . now()->format('Y-m-d H:i:s');

				$mutationParts[] = <<<GQL
					tagMutation$index: tagsAdd(id: \$$variableName, tags: [\$tag]) {
						node {
							id
							... on Order {
								tags
							}
						}
						userErrors {
							field
							message
						}
					}
				GQL;

				$mutationParts[] = <<<GQL
					noteMutation$index: orderUpdate(input: {id: \$$variableName, note: \$$noteVarName}) {
						order {
							id
							note
						}
						userErrors {
							field
							message
						}
					}
				GQL;
			}

			$allVariableDeclarations = implode(', ', $variableDeclarations);
			$allMutationParts = implode("\n", $mutationParts);

			$query = <<<GQL
				mutation BulkAddTagsAndNotes(\$tag: String!, $allVariableDeclarations) {
					$allMutationParts
				}
			GQL;

			$response = Http::withHeaders([
				'X-Shopify-Access-Token' => $accessToken,
				'Content-Type' => 'application/json',
			])->post("{$shopDomain}/admin/api/2024-01/graphql.json", [
				'query' => $query,
				'variables' => $variables,
			]);

			if (!$response->successful()) {
				\Log::error('Shopify Tag/Note Error: ' . $response->body());
			}
		}
	}
}



// Helper functions (can go outside the class as global helpers if needed)
function implodeWithCommas(array $items): string
{
	return implode(', ', $items);
}
function implodeWithNewLines(array $items): string
{
	return implode("\n", $items);
}

// Helper to build variable declarations like $id0: ID!, $id1: ID!, ...
function buildVariableDeclarations(array $orderGIDs): string
{
	$declarations = [];
	foreach ($orderGIDs as $index => $_) {
		$declarations[] = "\$id$index: ID!";
	}
	return implode(', ', $declarations);
}



// if (!function_exists('fulfillShopifyOrder')) {
// 	function fulfillShopifyOrder($shopifyOrderId)
// 	{
// 		// $shop = env('SHOP_DOMAIN'); // e.g., yourshop.myshopify.com
// 		// $token = env('ACCESS_TOKEN');
// 		[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));

// 		// Step 1: Get Fulfillment Order ID from Shopify
// 		$orderResponse = Http::withHeaders([
// 			'X-Shopify-Access-Token' => $accessToken
// 		])->get("{$shopDomain}/admin/api/2023-10/orders/{$shopifyOrderId}/fulfillment_orders.json");

// 		if (!$orderResponse->successful()) {
// 			throw new \Exception('Failed to fetch fulfillment orders');
// 		}

// 		$fulfillmentOrderId = $orderResponse['fulfillment_orders'][0]['id'] ?? null;

// 		if (!$fulfillmentOrderId) {
// 			throw new \Exception('Fulfillment order ID not found');
// 		}

// 		// Step 2: Fulfill
// 		$fulfillResponse = Http::withHeaders([
// 			'X-Shopify-Access-Token' => $accessToken,
// 			'Content-Type' => 'application/json',
// 		])->post("{$shopDomain}/admin/api/2023-10/fulfillments.json", [
// 			'fulfillment' => [
// 				'message' => 'Order fulfilled via Accuracy Checker',
// 				'notify_customer' => true,
// 				'tracking_info' => [
// 					'number' => null,
// 					'url' => null,
// 					'company' => null
// 				],
// 				'line_items_by_fulfillment_order' => [
// 					[
// 						'fulfillment_order_id' => $fulfillmentOrderId,
// 					]
// 				]
// 			]
// 		]);

// 		if (!$fulfillResponse->successful()) {
// 			throw new \Exception('Failed to fulfill order');
// 		}

// 		return $fulfillResponse->json();
// 	}
// }

if (!function_exists('fulfillShopifyOrder')) {
    function fulfillShopifyOrder($shopifyOrderId)
    {
       	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));


        // Step 1: Get Fulfillment Order ID and its line items
        $orderResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken
        ])->get("{$shopDomain}/admin/api/2023-10/orders/{$shopifyOrderId}/fulfillment_orders.json");

        if (!$orderResponse->successful()) {
            throw new \Exception('Failed to fetch fulfillment orders');
        }

        $fulfillmentOrders = $orderResponse['fulfillment_orders'] ?? [];

        if (empty($fulfillmentOrders)) {
            throw new \Exception('No fulfillment orders found');
        }

        $lineItemsPayload = [];

        foreach ($fulfillmentOrders as $fulfillmentOrder) {
            $lineItems = $fulfillmentOrder['line_items'] ?? [];

            $itemsToFulfill = array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'quantity' => $item['quantity'], // fulfill full quantity
                ];
            }, $lineItems);

            $lineItemsPayload[] = [
                'fulfillment_order_id' => $fulfillmentOrder['id'],
                'fulfillment_order_line_items' => $itemsToFulfill
            ];
        }

        // Step 2: Fulfill with all items
        $fulfillResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post("{$shopDomain}/admin/api/2023-10/fulfillments.json", [
            'fulfillment' => [
                'message' => 'Order fulfilled via Accuracy Checker',
                'notify_customer' => true,
                'tracking_info' => [
                    'number' => null,
                    'url' => null,
                    'company' => null
                ],
                'line_items_by_fulfillment_order' => $lineItemsPayload
            ]
        ]);

        if (!$fulfillResponse->successful()) {
            throw new \Exception('Failed to fulfill order: ' . $fulfillResponse->body());
        }

        return $fulfillResponse->json();
    }
}


// function getProductImages($shopifyOrderId, $productId)
// {
// 	[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));

// 	$url = "{$shopDomain}/admin/api/2024-01/products/{$productId}/images.json";

// 	$response = Http::withHeaders([
// 		'X-Shopify-Access-Token' => $accessToken,
// 		'Content-Type' => 'application/json',
// 	])->get($url);

// 	if ($response->successful()) {
// 		$images = $response->json('images');

// 		// Return array of image src URLs
// 		return collect($images)->pluck('src')->all();
// 	} else {
// 		throw new \Exception('Failed to fetch product images: ' . $response->body());
// 	}
// }

function getProductImages($shopifyOrderId, $productId)
{
    [$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));

    $url = "{$shopDomain}/admin/api/2024-01/graphql.json";

    $query = <<<GRAPHQL
    {
      product(id: "gid://shopify/Product/{$productId}") {
        images(first: 10) {
          edges {
            node {
              src
            }
          }
        }
      }
    }
    GRAPHQL;

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $accessToken,
        'Content-Type' => 'application/json',
    ])->post($url, [
        'query' => $query,
    ]);

    if ($response->successful()) {
        $edges = $response->json('data.product.images.edges') ?? [];

        return collect($edges)->pluck('node.src')->all();
    } else {
        throw new \Exception('Failed to fetch product images: ' . $response->body());
    }
}

//add timeline notes on orders when approveduse Illuminate\Support\Facades\Http;

if (!function_exists('triggerShopifyTimelineNote')) {
	function triggerShopifyTimelineNote($shopifyOrderId)
	{
		[$shopDomain, $accessToken] = array_values(getShopifyCredentialsByOrderId($shopifyOrderId));

		$user = Auth::user();
		$userName = $user?->name ?? 'System';
		$roleName =	$user?->roles?->first()?->name;
		$timestamp = now()->format('Y-m-d H:i:s');

		if ($roleName === 'ACT') {
			$note = "Order accurately_checked by: {$userName} on {$timestamp}";
		} else {
			$note = "Order approved by: {$userName} on {$timestamp}";
		}

		try {
			$response = Http::withHeaders([
				'X-Shopify-Access-Token' => $accessToken,
				'Content-Type' => 'application/json',
			])->put("{$shopDomain}/admin/api/2023-10/orders/{$shopifyOrderId}.json", [
				'order' => [
					'id' => $shopifyOrderId,
					'note' => $note,
				],
			]);

			if (!$response->successful()) {
				\Log::error("Shopify note update failed for Order {$shopifyOrderId}: " . $response->body());
				return false;
			}

			return true;
		} catch (\Exception $e) {
			\Log::error("Shopify note update exception for Order {$shopifyOrderId}: " . $e->getMessage());
			return false;
		}
	}
}


// function getPrescriberStatus($order_id){
// 	$response = OrderAction::where(['order_id'=>$order_id,'role'=>'Prescriber'])->orderBy('id', 'DESC')->first();
// 	return $response && isset($response->decision_status) ? $response->decision_status : '-';
// }
// function getCheckerStatus($order_id){
// 	$response = OrderAction::where(['order_id'=>$order_id,'role'=>'Checker'])->orderBy('id', 'DESC')->first();
// 	return $response && isset($response->decision_status) ? $response->decision_status : '-';
// }
// function getDispenserStatus($order_id){
// 	$response = OrderAction::where(['order_id'=>$order_id,'role'=>'Dispenser'])->orderBy('id', 'DESC')->first();
// 	return $response && isset($response->decision_status) ? $response->decision_status : '-';
// }
// function getACTStatus($order_id){
// 	$response = OrderAction::where(['order_id'=>$order_id,'role'=>'ACT'])->orderBy('id', 'DESC')->first();
// 	return $response && isset($response->decision_status) ? $response->decision_status : '-';
// }

function getPrescriberData($order_id){
	$response = OrderAction::where(['order_id'=>$order_id,'role'=>'Prescriber'])->orderBy('id', 'DESC')->first();
	return $response;
}

if (!function_exists('getUserName')) {
    function getUserName($userId)
    {
        $user = User::find($userId);
      	return $user ? $user->name : null;
    }
}

if (!function_exists('getStoreId')) {
    function getStoreId($order_id)
    {
        $order = Order::where('order_number',$order_id)->first();
      	return $order ? $order->store_id : null;
    }
}