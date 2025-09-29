<?php

namespace App\Traits;

use App\{
    Models\Setting,
    Models\PromoCode,
    Models\TrackOrder,
    Helpers\EmailHelper,
    Helpers\PriceHelper,
    Models\Notification,
    Models\PaymentSetting,
};
use App\Helpers\SmsHelper;
use App\Jobs\EmailSendJob;
use App\Models\Item;
use App\Models\Order;
use App\Models\ShippingService;
use App\Models\State;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use DodoPayments\Client;

use function GuzzleHttp\json_decode;

trait DodoPaymentsCheckout
{

    public function __construct()
    {
        $data = PaymentSetting::whereUniqueKeyword('dodopayments')->first();
        if ($data) {
            $paydata = $data->convertJsonData();
            Config::set('services.dodopayments.api_key', $paydata['api_key']);
            Config::set('services.dodopayments.webhook_secret', $paydata['webhook_secret']);
        }
    }

    public function dodoPaymentsSubmit($data)
    {
        $user = Auth::user();
        $setting = Setting::first();
        $cart = Session::get('cart');

        $total_tax = 0;
        $cart_total = 0;
        $total = 0;
        $option_price = 0;

        foreach ($cart as $key => $items) {
            $total += $items['main_price'] * $items['qty'];
            $option_price += $items['attribute_price'];
            $cart_total = $total + $option_price;
            $item = Item::findOrFail($key);
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item) * $items['qty'];
            }
        }

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        if (!PriceHelper::Digital()) {
            $shipping = null;
        } else {
            $shipping = isset($data['shipping_id']) && $data['shipping_id'] ? ShippingService::findOrFail($data['shipping_id']) : null;
        }

        $orderData['state'] = (isset($data['state_id']) && $data['state_id']) ? json_encode(State::findOrFail($data['state_id']), true) : null;
        $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $grand_total += PriceHelper::StatePrce(isset($data['state_id']) ? $data['state_id'] : null, $cart_total);
        $total_amount = PriceHelper::setConvertPrice($grand_total);

        $orderData['cart'] = json_encode($cart, true);
        $orderData['discount'] = json_encode($discount, true);
        $orderData['shipping'] = json_encode($shipping, true);
        $orderData['tax'] = $total_tax;
        $orderData['state_price'] = PriceHelper::StatePrce(isset($data['state_id']) ? $data['state_id'] : null, $cart_total);
        $orderData['shipping_info'] = json_encode(Session::get('shipping_address'), true);
        $orderData['billing_info'] = json_encode(Session::get('billing_address'), true);
        $orderData['payment_method'] = 'DodoPayments';
        $orderData['user_id'] = isset($user) ? $user->id : 0;
        $orderData['transaction_number'] = Str::random(10);
        $orderData['currency_sign'] = PriceHelper::setCurrencySign();
        $orderData['currency_value'] = PriceHelper::setCurrencyValue();
        $orderData['order_status'] = 'Pending';

        try {
            // Get DodoPayments settings from database
            $dodoSettings = \App\Models\PaymentSetting::whereUniqueKeyword('dodopayments')->first();
            if (!$dodoSettings) {
                \Log::error('DodoPayments: Settings not found in database');
                throw new \Exception('DodoPayments settings not found');
            }
            
            \Log::info('DodoPayments: Settings found', [
                'status' => $dodoSettings->status,
                'has_data' => !empty($dodoSettings->convertJsonData())
            ]);
            
            $dodoData = $dodoSettings->convertJsonData();
            $apiKey = $dodoData['api_key'] ?? null;
            
            if (empty($apiKey)) {
                \Log::error('DodoPayments: API key is empty', [
                    'dodo_data' => $dodoData
                ]);
                throw new \Exception('DodoPayments API key is not configured');
            }
            
            \Log::info('DodoPayments: API key found', [
                'api_key_length' => strlen($apiKey),
                'api_key_prefix' => substr($apiKey, 0, 10) . '...'
            ]);
            
            // Prepare billing address
            $billingAddress = Session::get('billing_address');
            $shippingAddress = Session::get('shipping_address');
            
            // Store order data in session for later processing
            Session::put('order_data', $orderData);
            Session::put('order_input_data', $data);
            Session::put('dodopayments_order_id', $orderData['transaction_number']);
            
            // For localhost development, create a mock payment
            if (app()->environment('local') || str_contains(request()->getHost(), 'localhost')) {
                \Log::info('DodoPayments: Using localhost mock payment');
                
                $mockPaymentId = 'mock_payment_' . time() . '_' . $orderData['transaction_number'];
                
                // For mock payments, immediately process the order since there's no real payment gateway
                \Log::info('DodoPayments: Processing mock payment order immediately');
                
                // Process the order immediately for mock payments
                $cart = Session::get('cart');
                $user = Auth::user();
                $total_tax = 0;
                $cart_total = 0;
                $total = 0;
                $option_price = 0;

                foreach ($cart as $key => $items) {
                    $total += $items['main_price'] * $items['qty'];
                    $option_price += $items['attribute_price'];
                    $cart_total = $total + $option_price;
                    $item = Item::findOrFail($key);
                    if ($item->tax) {
                        $total_tax += $item::taxCalculate($item) * $items['qty'];
                    }
                }

                $order_input_data = Session::get('order_input_data');
                if (!PriceHelper::Digital()) {
                    $shipping = null;
                } else {
                    $shipping = (isset($order_input_data['shipping_id']) && $order_input_data['shipping_id']) ? ShippingService::findOrFail($order_input_data['shipping_id']) : null;
                }
                $discount = [];
                if (Session::has('coupon')) {
                    $discount = Session::get('coupon');
                }

                $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
                $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
                $grand_total += PriceHelper::StatePrce(isset($order_input_data['state_id']) ? $order_input_data['state_id'] : null, $cart_total);

                $total_amount = PriceHelper::setConvertPrice($grand_total);

                $orderData['txnid'] = $mockPaymentId;
                $orderData['payment_status'] = 'Paid';

                $order = Order::create($orderData);

                $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
                $order->transaction_number = $new_txn;
                $order->save();

                PriceHelper::Transaction($order->id, $order->transaction_number, EmailHelper::getEmail(), PriceHelper::OrderTotal($order, 'trns'));
                PriceHelper::LicenseQtyDecrese($cart);
                PriceHelper::LicenseQtyDecrese($cart);

                if (Session::has('copon')) {
                    $code = PromoCode::find(Session::get('copon')['code']['id']);
                    $code->no_of_times--;
                    $code->update();
                }

                if ($discount) {
                    $coupon_id = $discount['code']['id'];
                    $get_coupon = PromoCode::findOrFail($coupon_id);
                    $get_coupon->no_of_times -= 1;
                    $get_coupon->update();
                }

                TrackOrder::create([
                    'title' => 'Pending',
                    'order_id' => $order->id,
                ]);

                Notification::create([
                    'order_id' => $order->id
                ]);

                \Log::info('DodoPayments: Mock payment order created successfully', [
                    'order_id' => $order->id,
                    'transaction_number' => $order->transaction_number
                ]);
                
                return [
                    'status' => true,
                    'payment_id' => $mockPaymentId,
                    'overlay_checkout' => true,
                    'api_key' => $apiKey,
                    'mock_payment' => true,
                    'order_created' => true,
                    'order_id' => $order->id
                ];
            }
            
            // Initialize DodoPayments client
            \Log::info('DodoPayments: Initializing client with API key');
            $client = new Client($apiKey);
            \Log::info('DodoPayments: Client initialized successfully');
            
            // Determine if we should use test or live mode based on API key
            $baseUrl = str_starts_with($apiKey, 'test_') ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
            \Log::info('DodoPayments: Using base URL', ['base_url' => $baseUrl]);
            
            // Test basic connectivity to DodoPayments API (skip health check as endpoint may not exist)
            try {
                \Log::info('DodoPayments: Testing API connectivity via products endpoint');
                $testResponse = \Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->get($baseUrl . '/products');
                \Log::info('DodoPayments: API connectivity test completed', [
                    'status' => $testResponse->status(),
                    'response_length' => strlen($testResponse->body()),
                    'url' => $baseUrl . '/products'
                ]);
            } catch (\Exception $e) {
                \Log::warning('DodoPayments: API connectivity test failed', [
                    'message' => $e->getMessage(),
                    'url' => $baseUrl . '/products'
                ]);
            }
            
            // Test the correct payments endpoint with authentication
            try {
                \Log::info('DodoPayments: Testing payments endpoint');
                $paymentsResponse = \Http::timeout(5)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->get($baseUrl . '/payments');
                \Log::info('DodoPayments: Payments endpoint test', [
                    'endpoint' => $baseUrl . '/payments',
                    'status' => $paymentsResponse->status(),
                    'response_preview' => substr($paymentsResponse->body(), 0, 200)
                ]);
            } catch (\Exception $e) {
                \Log::warning('DodoPayments: Payments endpoint test failed', [
                    'endpoint' => $baseUrl . '/payments',
                    'error' => $e->getMessage()
                ]);
            }
            
            // Prepare parameters for DodoPayments SDK
            $billing = [
                'city' => $billingAddress['bill_city'] ?? 'City',
                'country' => $billingAddress['bill_country'] ?? 'US',
                'state' => $billingAddress['bill_state'] ?? 'State',
                'street' => $billingAddress['bill_address'] ?? 'Street',
                'zipcode' => $billingAddress['bill_zip'] ?? '12345'
            ];
            
            $customer = [
                'customer_id' => isset($user) ? (string) $user->id : 'guest_' . time(),
                'email' => $billingAddress['bill_email'] ?? $shippingAddress['ship_email'] ?? '',
                'name' => ($billingAddress['bill_first_name'] ?? '') . ' ' . ($billingAddress['bill_last_name'] ?? '')
            ];
            
            $productCart = [
                [
                    'product_id' => 'generic_order_' . time(),  // Use a generic product ID
                    'quantity' => 1,  // Keep as integer (u32)
                    'name' => $setting->title . ' Order',
                    'price' => [
                        'amount' => (int) round($total_amount * 100),  // Convert to cents (integer)
                        'currency' => 'USD',
                        'type' => 'one_time_price'
                    ]
                ]
            ];
            
            $metadata = [
                'order_id' => $orderData['transaction_number'],
                'user_id' => (string) $orderData['user_id']  // Convert to string as required by API
            ];
            
            $returnURL = route('front.checkout.redirect');
            
            // Create RequestOptions with timeout
            $requestOptions = new \Dodopayments\RequestOptions();
            $requestOptions->timeout = 30; // 30 seconds timeout
            
            \Log::info('DodoPayments: About to create payment', [
                'api_key_length' => strlen($apiKey),
                'api_key_prefix' => substr($apiKey, 0, 10) . '...',
                'settings_found' => $dodoSettings ? 'yes' : 'no',
                'billing' => $billing,
                'customer' => $customer,
                'product_cart' => $productCart,
                'return_url' => $returnURL,
                'timeout' => $requestOptions->timeout
            ]);
            
            // Use Checkout Sessions approach as per the latest documentation
            try {
                \Log::info('DodoPayments: Using Checkout Sessions approach');
                
                // Use the official DodoPayments API structure from documentation
                $productStructures = [
                    // Structure 1: Official API structure with all required fields
                    [
                        'name' => $setting->title . ' Order',
                        'description' => 'Order from ' . $setting->title,
                        'price' => [
                            'currency' => 'USD',
                            'price' => (int) round($total_amount * 100),
                            'type' => 'one_time_price',
                            'discount' => 0,
                            'purchasing_power_parity' => true
                        ],
                        'tax_category' => 'digital_products'
                    ],
                    // Structure 2: Simplified version
                    [
                        'name' => $setting->title . ' Order',
                        'price' => [
                            'currency' => 'USD',
                            'price' => (int) round($total_amount * 100),
                            'type' => 'one_time_price'
                        ],
                        'tax_category' => 'digital_products'
                    ],
                    // Structure 3: With saas tax category
                    [
                        'name' => $setting->title . ' Order',
                        'price' => [
                            'currency' => 'USD',
                            'price' => (int) round($total_amount * 100),
                            'type' => 'one_time_price'
                        ],
                        'tax_category' => 'saas'
                    ],
                    // Structure 4: With e_book tax category
                    [
                        'name' => $setting->title . ' Order',
                        'price' => [
                            'currency' => 'USD',
                            'price' => (int) round($total_amount * 100),
                            'type' => 'one_time_price'
                        ],
                        'tax_category' => 'e_book'
                    ]
                ];
                
                $productCreated = false;
                $productId = null;
                
                foreach ($productStructures as $index => $productData) {
                try {
                        \Log::info('DodoPayments: Trying product structure ' . ($index + 1), ['product_data' => $productData]);
                        
                    $productResponse = \Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ])
                        ->post($baseUrl . '/products', $productData);
                    
                        \Log::info('DodoPayments: Product creation response ' . ($index + 1), [
                        'status' => $productResponse->status(),
                        'response_body' => $productResponse->body()
                    ]);
                    
                    if ($productResponse->successful()) {
                        $productResult = $productResponse->json();
                        $productId = $productResult['id'] ?? $productResult['product_id'] ?? null;
                        
                        if ($productId) {
                                \Log::info('DodoPayments: Product created successfully with structure ' . ($index + 1), [
                                'product_id' => $productId
                            ]);
                            
                                $productCreated = true;
                                break; // Exit the loop since we successfully created a product
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('DodoPayments: Product structure ' . ($index + 1) . ' failed', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                if (!$productCreated || !$productId) {
                    \Log::warning('DodoPayments: All product creation structures failed');
                    throw new \Exception('Product creation failed');
                }
                
                // Now create a Checkout Session using the correct endpoint from documentation
                $checkoutSessionPayload = [
                    'product_cart' => [
                        [
                            'product_id' => $productId,
                            'quantity' => 1
                        ]
                    ],
                    'customer' => [
                        'email' => $customer['email'],
                        'name' => $customer['name']
                    ],
                    'billing_address' => [
                        'street' => $billing['street'],
                        'city' => $billing['city'],
                        'state' => $billing['state'],
                        'country' => $billing['country'],
                        'zipcode' => $billing['zipcode']
                    ],
                    'return_url' => $returnURL,
                    'metadata' => $metadata
                ];
                
                \Log::info('DodoPayments: Creating checkout session with correct endpoint', ['payload' => $checkoutSessionPayload]);
                
                $checkoutResponse = \Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($baseUrl . '/checkouts', $checkoutSessionPayload);
                
                \Log::info('DodoPayments: Checkout session response', [
                    'status' => $checkoutResponse->status(),
                    'response_body' => $checkoutResponse->body()
                ]);
                
                if ($checkoutResponse->successful()) {
                    $checkoutData = $checkoutResponse->json();
                    $checkoutUrl = $checkoutData['checkout_url'] ?? null;
                    $sessionId = $checkoutData['session_id'] ?? null;
                    
                    \Log::info('DodoPayments: Checkout session response data', [
                        'checkout_data' => $checkoutData,
                        'checkout_url' => $checkoutUrl,
                        'session_id' => $sessionId,
                        'has_checkout_url' => !empty($checkoutUrl),
                        'has_session_id' => !empty($sessionId)
                    ]);
                    
                    if ($checkoutUrl && $sessionId) {
                        \Log::info('DodoPayments: Checkout session created successfully - RETURNING RESPONSE', [
                            'session_id' => $sessionId,
                            'checkout_url' => $checkoutUrl
                        ]);
                        
                        // Return success with checkout URL for redirect
                        return [
                            'status' => true,
                            'payment_id' => $sessionId, // Use session ID for reference
                            'checkout_url' => $checkoutUrl,
                            'overlay_checkout' => true,
                            'api_key' => $apiKey
                        ];
                    } else {
                        \Log::warning('DodoPayments: Checkout session created but missing required fields', [
                            'has_checkout_url' => !empty($checkoutUrl),
                            'has_session_id' => !empty($sessionId),
                            'checkout_url_value' => $checkoutUrl,
                            'session_id_value' => $sessionId
                        ]);
                    }
                } else {
                    \Log::warning('DodoPayments: Checkout session creation failed', [
                        'status' => $checkoutResponse->status(),
                        'response_body' => $checkoutResponse->body()
                    ]);
                    
                    // Fallback to payment creation if checkout sessions fail
                    \Log::info('DodoPayments: Falling back to payment creation - checkout session creation failed');
                    
                    $paymentPayload = [
                        'payment_link' => true,
                        'billing' => $billing,
                        'customer' => $customer,
                        'product_cart' => [
                            [
                                'product_id' => $productId,
                                'quantity' => 1
                            ]
                        ],
                        'return_url' => $returnURL,
                        'metadata' => $metadata
                    ];
                    
                    \Log::info('DodoPayments: Creating payment with real product', ['payload' => $paymentPayload]);
                    
                    $paymentResponse = \Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ])
                        ->post($baseUrl . '/payments', $paymentPayload);
                    
                    \Log::info('DodoPayments: Payment creation response', [
                        'status' => $paymentResponse->status(),
                        'response_body' => $paymentResponse->body()
                    ]);
                    
                    if ($paymentResponse->successful()) {
                        $paymentData = $paymentResponse->json();
                        $paymentId = $paymentData['payment_id'] ?? $paymentData['id'] ?? $paymentData['checkout_session_id'] ?? null;
                        $checkoutUrl = $paymentData['checkout_url'] ?? $paymentData['payment_url'] ?? null;
                        
                        if ($paymentId) {
                            \Log::info('DodoPayments: Payment created successfully', [
                                'payment_id' => $paymentId,
                                'checkout_url' => $checkoutUrl
                            ]);
                            
                            // Return success with payment ID for overlay checkout
                            return [
                                'status' => true,
                                'payment_id' => $paymentId,
                                'checkout_url' => $checkoutUrl,
                                'overlay_checkout' => true,
                                'api_key' => $apiKey
                            ];
                        }
                    }
                }
                
                \Log::info('DodoPayments: Falling back to SDK method');
                
            } catch (\Exception $e) {
                \Log::warning('DodoPayments: Checkout Sessions approach failed, trying SDK', [
                    'message' => $e->getMessage()
                ]);
            }
            
            // Create payment using the official SDK with payment_link=true
            try {
                // If we don't have a product ID from the HTTP call, try to create one via SDK or use a different approach
                if (empty($productCart[0]['product_id']) || str_contains($productCart[0]['product_id'], 'generic_order_')) {
                    \Log::info('DodoPayments: No valid product ID, trying to create product via SDK or use alternative approach');
                    
                    // Try to create a simple payment without product_cart
                    $paymentPayload = [
                        'payment_link' => true,
                            'amount' => (int) round($total_amount * 100),
                            'currency' => 'USD',
                        'billing' => $billing,
                            'customer' => $customer,
                            'return_url' => $returnURL,
                            'metadata' => $metadata
                        ];
                        
                    \Log::info('DodoPayments: Trying payment without product_cart', ['payload' => $paymentPayload]);
                            
                    $simpleResponse = \Http::timeout(30)
                                ->withHeaders([
                                    'Authorization' => 'Bearer ' . $apiKey,
                                    'Content-Type' => 'application/json',
                                    'Accept' => 'application/json'
                                ])
                        ->post($baseUrl . '/payments', $paymentPayload);
                    
                    \Log::info('DodoPayments: Simple payment response', [
                        'status' => $simpleResponse->status(),
                        'response_body' => $simpleResponse->body()
                    ]);
                    
                    if ($simpleResponse->successful()) {
                        $responseData = $simpleResponse->json();
                        $paymentId = $responseData['payment_id'] ?? $responseData['id'] ?? $responseData['checkout_session_id'] ?? null;
                        
                        if ($paymentId) {
                            \Log::info('DodoPayments: Simple payment created successfully', [
                                'payment_id' => $paymentId
                            ]);
                            
                            return [
                                'status' => true,
                                'payment_id' => $paymentId,
                                'overlay_checkout' => true,
                                'api_key' => $apiKey
                            ];
                        }
                    }
                }
                
                \Log::info('DodoPayments: Calling payments->create() via SDK with payment_link');
                \Log::info('DodoPayments: SDK parameters', [
                    'billing' => $billing,
                    'customer' => $customer,
                    'product_cart' => $productCart,
                    'metadata' => $metadata,
                    'return_url' => $returnURL,
                    'payment_link' => true
                ]);
                
                // Set a maximum execution time for this specific operation
                $startTime = microtime(true);
                set_time_limit(60); // Allow up to 60 seconds for this operation
                
                $payment = $client->payments->create(
                    $billing,
                    $customer,
                    $productCart,
                    null, // allowedPaymentMethodTypes
                    null, // billingCurrency
                    null, // discountCode
                    $metadata,
                    true, // paymentLink = true as per documentation
                    $returnURL,
                    null, // showSavedPaymentMethods
                    null, // taxID
                    $requestOptions // RequestOptions with timeout
                );
                
                $endTime = microtime(true);
                $executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
                
                \Log::info('DodoPayments: payments->create() completed', [
                    'payment_object' => $payment ? 'received' : 'null',
                    'payment_id' => $payment->payment_id ?? 'no_payment_id',
                    'execution_time_ms' => $executionTime,
                    'payment_type' => get_class($payment) ?? 'unknown'
                ]);
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                \Log::error('DodoPayments: Connection timeout', [
                    'message' => $e->getMessage(),
                    'timeout' => $requestOptions->timeout,
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Connection to DodoPayments timed out. Please try again.');
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                \Log::error('DodoPayments: Request failed', [
                    'message' => $e->getMessage(),
                    'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response',
                    'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'no_status',
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('DodoPayments request failed: ' . $e->getMessage());
            } catch (\Exception $e) {
                \Log::error('DodoPayments: General exception during payment creation', [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // If SDK fails, try one more direct HTTP call as last resort
                \Log::info('DodoPayments: SDK failed, trying final direct HTTP call');
                try {
                    $finalProductCart = [
                        [
                            'product_id' => 'final_payment_' . time(),
                            'quantity' => 1,
                            'name' => $setting->title . ' Order',
                            'price' => [
                                'amount' => (int) round($total_amount * 100),
                                'currency' => 'USD',
                                'type' => 'one_time_price'
                            ]
                        ]
                    ];
                    
                    $finalPayload = [
                        'payment_link' => true,
                        'customer' => $customer,
                        'billing' => $billing,
                        'product_cart' => $finalProductCart,
                        'return_url' => $returnURL,
                        'metadata' => $metadata
                    ];
                    
                    $finalResponse = \Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ])
                        ->post($baseUrl . '/payments', $finalPayload);
                    
                    \Log::info('DodoPayments: Final HTTP call result', [
                        'status' => $finalResponse->status(),
                        'response_body' => $finalResponse->body()
                    ]);
                    
                    if ($finalResponse->successful()) {
                        $responseData = $finalResponse->json();
                        $paymentId = $responseData['payment_id'] ?? $responseData['id'] ?? $responseData['checkout_session_id'] ?? null;
                        
                        if ($paymentId) {
                            \Log::info('DodoPayments: Final HTTP call successful', [
                                'payment_id' => $paymentId
                            ]);
                            
                            return [
                                'status' => true,
                                'payment_id' => $paymentId,
                                'overlay_checkout' => true,
                                'api_key' => $apiKey
                            ];
                        }
                    }
                } catch (\Exception $finalException) {
                    \Log::error('DodoPayments: Final HTTP call also failed', [
                        'message' => $finalException->getMessage()
                    ]);
                }
                
                throw $e;
            }
            
            \Log::info('DodoPayments payment created successfully', [
                'payment_id' => $payment->payment_id ?? 'unknown',
                'order_id' => $orderData['transaction_number']
            ]);
            
            // Return payment data for overlay checkout instead of redirect URL
            if (isset($payment->payment_id)) {
                return [
                    'status' => true,
                    'payment_id' => $payment->payment_id,
                    'overlay_checkout' => true,
                    'api_key' => $apiKey
                ];
            } else {
                \Log::error('DodoPayments payment created but no payment_id returned', [
                    'payment_object' => $payment
                ]);
                return [
                    'status' => false,
                    'message' => 'Payment created but no payment ID received'
                ];
            }
            
        } catch (\Exception $e) {
            \Log::error('DodoPayments Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'api_key' => substr($apiKey, 0, 10) . '...',
                'environment' => app()->environment()
            ]);
            
            // Provide user-friendly error messages
            $errorMessage = 'Payment initialization failed';
            if (str_contains($e->getMessage(), 'Connection') || str_contains($e->getMessage(), 'timeout')) {
                $errorMessage = 'Unable to connect to payment service. Please check your internet connection and try again.';
            } elseif (str_contains($e->getMessage(), 'API key') || str_contains($e->getMessage(), 'authentication')) {
                $errorMessage = 'Payment service configuration error. Please contact support.';
            } elseif (str_contains($e->getMessage(), 'currency') || str_contains($e->getMessage(), 'amount')) {
                $errorMessage = 'Invalid payment amount or currency. Please try again.';
            } else {
                $errorMessage = 'Payment service temporarily unavailable. Please try again later.';
            }
            
            return [
                'status' => false,
                'message' => $errorMessage
            ];
        }
    }

    public function dodoPaymentsNotify($resData)
    {
        try {
            $webhookSecret = Config::get('services.dodopayments.webhook_secret');
            
            // Check if this is a webhook or redirect
            if (request()->isMethod('post')) {
                // Webhook handling
                $signature = request()->header('DodoPayments-Signature');
                $payload = request()->getContent();
                
                if ($webhookSecret) {
                    $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
                    
                    if (!hash_equals($signature, $expectedSignature)) {
                        \Log::warning('DodoPayments webhook signature mismatch');
                        return [
                            'status' => false,
                            'message' => 'Invalid webhook signature'
                        ];
                    }
                }
                
                $webhookData = json_decode($payload, true);
                
                // Handle different webhook events according to DodoPayments API
                if (isset($webhookData['event'])) {
                    if ($webhookData['event'] === 'payment.completed' || $webhookData['event'] === 'payment.succeeded') {
                        $orderId = $webhookData['data']['metadata']['order_id'] ?? $webhookData['data']['id'] ?? null;
                        
                        // Verify this is our order
                        if (Session::get('dodopayments_order_id') !== $orderId) {
                            \Log::warning('DodoPayments webhook order ID mismatch', [
                                'expected' => Session::get('dodopayments_order_id'),
                                'received' => $orderId,
                                'webhook_data' => $webhookData
                            ]);
                            return [
                                'status' => false,
                                'message' => 'Order ID mismatch'
                            ];
                        }
                    } else {
                        \Log::info('DodoPayments webhook received non-payment event', [
                            'event' => $webhookData['event'],
                            'data' => $webhookData['data'] ?? null
                        ]);
                        return [
                            'status' => false,
                            'message' => 'Non-payment webhook event received'
                        ];
                    }
                }
            } else {
                // Redirect handling - check query parameters
                $paymentId = request()->get('payment_id');
                $status = request()->get('status');
                
                if ($status === 'success' && $paymentId) {
                    // For redirects, we'll process the order since DodoPayments redirected successfully
                    \Log::info('DodoPayments redirect success', [
                        'payment_id' => $paymentId,
                        'status' => $status
                    ]);
                } else {
                    \Log::warning('DodoPayments redirect with missing parameters', [
                        'payment_id' => $paymentId,
                        'status' => $status,
                        'all_params' => request()->all()
                    ]);
                    return [
                        'status' => false,
                        'message' => 'Payment not completed or missing parameters'
                    ];
                }
            }
            
            // Process the order since payment was successful
            $cart = Session::get('cart');
            $user = Auth::user();
            $total_tax = 0;
            $cart_total = 0;
            $total = 0;
            $option_price = 0;

            foreach ($cart as $key => $items) {
                $total += $items['main_price'] * $items['qty'];
                $option_price += $items['attribute_price'];
                $cart_total = $total + $option_price;
                $item = Item::findOrFail($key);
                if ($item->tax) {
                    $total_tax += $item::taxCalculate($item) * $items['qty'];
                }
            }

            $order_input_data = Session::get('order_input_data');
            if (!PriceHelper::Digital()) {
                $shipping = null;
            } else {
                $shipping = (isset($order_input_data['shipping_id']) && $order_input_data['shipping_id']) ? ShippingService::findOrFail($order_input_data['shipping_id']) : null;
            }
            $discount = [];
            if (Session::has('coupon')) {
                $discount = Session::get('coupon');
            }

            $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
            $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
            $grand_total += PriceHelper::StatePrce(isset($order_input_data['state_id']) ? $order_input_data['state_id'] : null, $cart_total);

            $total_amount = PriceHelper::setConvertPrice($grand_total);

            $orderData = Session::get('order_data');
            $orderData['txnid'] = $paymentId ?? 'dodopayments_' . time();
            $orderData['payment_status'] = 'Paid';

            $order = Order::create($orderData);

            $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
            $order->transaction_number = $new_txn;
            $order->save();

            PriceHelper::Transaction($order->id, $order->transaction_number, EmailHelper::getEmail(), PriceHelper::OrderTotal($order, 'trns'));
            PriceHelper::LicenseQtyDecrese($cart);
            PriceHelper::LicenseQtyDecrese($cart);

            if (Session::has('copon')) {
                $code = PromoCode::find(Session::get('copon')['code']['id']);
                $code->no_of_times--;
                $code->update();
            }

            if ($discount) {
                $coupon_id = $discount['code']['id'];
                $get_coupon = PromoCode::findOrFail($coupon_id);
                $get_coupon->no_of_times -= 1;
                $get_coupon->update();
            }

            TrackOrder::create([
                'title' => 'Pending',
                'order_id' => $order->id,
            ]);

            Notification::create([
                'order_id' => $order->id
            ]);

            $setting = Setting::first();
            if ($setting->is_twilio == 1) {
                // message
                $sms = new SmsHelper();
                $user_number = json_decode($order->billing_info, true)['bill_phone'];
                if ($user_number) {
                    $sms->SendSms($user_number, "'purchase'", $order->transaction_number);
                }
            }

            $emailData = [
                'to' => EmailHelper::getEmail(),
                'type' => "Order",
                'user_name' => isset($user) ? $user->displayName() : Session::get('billing_address')['bill_first_name'],
                'order_cost' => $total_amount,
                'transaction_number' => $order->transaction_number,
                'site_title' => Setting::first()->title,
            ];

            $setting = Setting::first();
            if ($setting->is_queue_enabled == 1) {
                dispatch(new EmailSendJob($emailData, "template"));
            } else {
                $email = new EmailHelper();
                $email->sendTemplateMail($emailData, "template");
            }
            
            Session::put('order_id', $order->id);
            Session::forget('cart');
            Session::forget('discount');
            Session::forget('order_data');
            Session::forget('order_payment_id');
            Session::forget('coupon');
            Session::forget('dodopayments_payment_id');
            
            return [
                'status' => true
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
