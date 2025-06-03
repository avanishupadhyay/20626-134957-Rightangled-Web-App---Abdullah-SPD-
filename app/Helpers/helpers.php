<?php

if (!function_exists('pr')) {
    
    function pr($data) {
		echo '<pre>';
        print_r($data);
		echo '</pre>';
    }
}


if (!function_exists('getConfigurationMenu')) {
    
	function getConfigurationMenu() {
		$configuration = new \App\Models\Configuration();
		return $configuration->getprefix();
	}
}


if (!function_exists('getTotalSubscribeUser')) {
    
	function getTotalSubscribeUser() {
		$userLogObj = new \App\Models\UserLog();
		return $userLogObj->count();
	}
}

if (!function_exists('getTotalUserOrder')) {
    
	function getTotalUserOrder() {
		$orderObj = new \App\Models\Order();
		return $orderObj->count();
	}
}

if (!function_exists('getTotalUserLoyality')) {
    
	function getTotalUserLoyality() {
		$loyalityObj = new \App\Models\Loyality();
		return $loyalityObj->count();
	}
}


if (!function_exists('getRecentSubscribers')) {
    
	function getRecentSubscribers() {
		$userLogObj = 	new \App\Models\UserLog();
		$userLogs	=	$userLogObj->orderBy('id','desc')->limit(10)->get();

		return $userLogs;
	}
}


if (!function_exists('getDraftOrderMutation')) {
    
	function getDraftOrderMutation() {
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
    function getUpdateDraftOrderMutation() {
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
    function getLastIntegerFromGid($gid) {
		/* gid example : gid://shopify/Customer/8474572914881 */
		if (preg_match('/(\d+)$/', $gid, $matches)) {
			return $matches[1]; /* Return the matched digits */
		}

		return null; 
	}
}

if (!function_exists('isLoyaltyUserExists')) {
    function isLoyaltyUserExists($email) {
		$userExists = \App\Models\UserLog::where('email', $email)->where('marketing_agreement',1)->exists();

		if($userExists){
			return true;
		}

		return false;
	}
}


if (!function_exists('isOrderExists')) {
    function isOrderExists($order_number) {
		$orderExists = \App\Models\Order::where('order_number', $order_number)->exists();

		if($orderExists){
			return true;
		}

		return false;
	}
}

function getCountryISDByCode($country_code){
	
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

	return !empty($isd_codes[$country_code]) ? $isd_codes[$country_code] : '44' ;
}




