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
            $shipping = ShippingService::findOrFail($data['shipping_id']);
        }

        $orderData['state'] = $data['state_id'] ? json_encode(State::findOrFail($data['state_id']), true) : null;
        $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $grand_total += PriceHelper::StatePrce($data['state_id'], $cart_total);
        $total_amount = PriceHelper::setConvertPrice($grand_total);

        $orderData['cart'] = json_encode($cart, true);
        $orderData['discount'] = json_encode($discount, true);
        $orderData['shipping'] = json_encode($shipping, true);
        $orderData['tax'] = $total_tax;
        $orderData['state_price'] = PriceHelper::StatePrce($data['state_id'], $cart_total);
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
                
                return [
                    'status' => true,
                    'payment_id' => $mockPaymentId,
                    'overlay_checkout' => true,
                    'api_key' => $apiKey,
                    'mock_payment' => true
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
                        'type' => 'one_time'
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
            
            // Try direct HTTP call first to test the API
            try {
                \Log::info('DodoPayments: Testing direct HTTP API call');
                
                // Try to create a product first, then use it for payment
                \Log::info('DodoPayments: Attempting to create product first');
                
                $productData = [
                    'name' => $setting->title . ' Order',
                    'price' => [
                        'amount' => (int) round($total_amount * 100),
                        'currency' => 'USD',
                        'type' => 'one_time'
                    ],
                    'type' => 'one_time'
                ];
                
                $productId = null;
                try {
                    $productResponse = \Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ])
                        ->post($baseUrl . '/products', $productData);
                    
                    \Log::info('DodoPayments: Product creation response', [
                        'status' => $productResponse->status(),
                        'response_body' => $productResponse->body()
                    ]);
                    
                    if ($productResponse->successful()) {
                        $productResult = $productResponse->json();
                        $productId = $productResult['id'] ?? $productResult['product_id'] ?? null;
                        
                        if ($productId) {
                            \Log::info('DodoPayments: Product created successfully', [
                                'product_id' => $productId
                            ]);
                            
                            // Update product_cart with the created product
                            $productCart = [
                                [
                                    'product_id' => $productId,
                                    'quantity' => 1,
                                    'name' => $setting->title . ' Order',
                                    'price' => [
                                        'amount' => (int) round($total_amount * 100),
                                        'currency' => 'USD',
                                        'type' => 'one_time'
                                    ]
                                ]
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('DodoPayments: Product creation failed', [
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Use correct endpoints based on DodoPayments documentation
                $endpoints = [
                    $baseUrl . '/payments',  // Correct endpoint for one-time payments
                ];
                
                $response = null;
                $usedEndpoint = null;
                
                foreach ($endpoints as $endpoint) {
                    try {
                        \Log::info('DodoPayments: Trying endpoint', ['endpoint' => $endpoint]);
                        
                        // Try different payload structures
                        $payloads = [];
                        
                        // If we have a valid product ID, try with product_cart first
                        if ($productId) {
                            $payloads[] = [
                                'billing' => $billing,
                                'customer' => $customer,
                                'product_cart' => $productCart,
                                'return_url' => $returnURL,
                                'metadata' => $metadata
                            ];
                            
                            $payloads[] = [
                                'amount' => (int) round($total_amount * 100),
                                'currency' => 'USD',
                                'customer' => $customer,
                                'billing' => $billing,
                                'product_cart' => $productCart,
                                'return_url' => $returnURL,
                                'metadata' => $metadata
                            ];
                        }
                        
                        // Always try simple payment structure as fallback
                        $payloads[] = [
                            'amount' => (int) round($total_amount * 100),
                            'currency' => 'USD',
                            'customer' => $customer,
                            'billing' => $billing,
                            'product_cart' => $productCart,
                            'return_url' => $returnURL,
                            'metadata' => $metadata
                        ];
                        
                        foreach ($payloads as $index => $payload) {
                            \Log::info('DodoPayments: Trying payload structure ' . ($index + 1), [
                                'payload' => $payload
                            ]);
                            
                            $response = \Http::timeout(30)
                                ->withHeaders([
                                    'Authorization' => 'Bearer ' . $apiKey,
                                    'Content-Type' => 'application/json',
                                    'Accept' => 'application/json'
                                ])
                                ->post($endpoint, $payload);
                            
                            \Log::info('DodoPayments: Payload response', [
                                'payload_index' => $index + 1,
                                'status' => $response->status(),
                                'response_body' => $response->body()
                            ]);
                            
                            if ($response->status() !== 404 && $response->status() !== 422) {
                                $usedEndpoint = $endpoint;
                                break 2; // Break out of both loops
                            }
                            
                            // If we get a 422 error, try the next payload structure
                            if ($response->status() === 422) {
                                continue;
                            }
                        }
                        
                        if ($response->status() === 404) {
                            continue; // Try next endpoint
                        }
                        
                    } catch (\Exception $e) {
                        \Log::warning('DodoPayments: Endpoint failed', [
                            'endpoint' => $endpoint,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                if ($response && $usedEndpoint) {
                    \Log::info('DodoPayments: Direct HTTP API call completed', [
                        'used_endpoint' => $usedEndpoint,
                        'status' => $response->status(),
                        'response_body' => $response->body(),
                        'response_size' => strlen($response->body())
                    ]);
                    
                    if ($response->successful()) {
                        $responseData = $response->json();
                        $paymentId = $responseData['payment_id'] ?? $responseData['id'] ?? $responseData['checkout_session_id'] ?? null;
                        
                        if ($paymentId) {
                            \Log::info('DodoPayments: Direct API call successful', [
                                'payment_id' => $paymentId,
                                'used_endpoint' => $usedEndpoint
                            ]);
                            
                            // Return success with payment ID for overlay checkout
                            return [
                                'status' => true,
                                'payment_id' => $paymentId,
                                'overlay_checkout' => true,
                                'api_key' => $apiKey
                            ];
                        }
                    }
                } else {
                    \Log::warning('DodoPayments: No working endpoint found - trying SDK method');
                }
                
                // If direct HTTP doesn't work, fall back to SDK
                \Log::info('DodoPayments: Falling back to SDK method');
                
            } catch (\Exception $e) {
                \Log::warning('DodoPayments: Direct HTTP call failed, trying SDK', [
                    'message' => $e->getMessage()
                ]);
            }
            
            // Create payment using the official SDK with correct parameters
            try {
                \Log::info('DodoPayments: Calling payments->create() via SDK');
                \Log::info('DodoPayments: SDK parameters', [
                    'billing' => $billing,
                    'customer' => $customer,
                    'product_cart' => $productCart,
                    'metadata' => $metadata,
                    'return_url' => $returnURL
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
                    null, // paymentLink
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
                    $finalPayload = [
                        'amount' => (int) round($total_amount * 100),
                        'currency' => 'USD',
                        'customer' => $customer,
                        'billing' => $billing,
                        'product_cart' => $productCart,
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
                $shipping = ShippingService::findOrFail($order_input_data['shipping_id']);
            }
            $discount = [];
            if (Session::has('coupon')) {
                $discount = Session::get('coupon');
            }

            $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
            $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
            $grand_total += PriceHelper::StatePrce($order_input_data['state_id'], $cart_total);

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
