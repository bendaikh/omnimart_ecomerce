<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiTransaction;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Models\Setting;
use App\Models\TrackOrder;
use App\Models\Notification;
use App\Helpers\PriceHelper;
use App\Helpers\EmailHelper;
use App\Helpers\SmsHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use DodoPayments\Client;

class PaymentController extends Controller
{
    /**
     * Initialize Dodopayments configuration
     */
    public function __construct()
    {
        $data = PaymentSetting::whereUniqueKeyword('dodopayments')->first();
        if ($data) {
            $paydata = $data->convertJsonData();
            Config::set('services.dodopayments.api_key', $paydata['api_key']);
            Config::set('services.dodopayments.webhook_secret', $paydata['webhook_secret']);
        }
    }

    /**
     * Create a new order and process payment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(Request $request)
    {
        try {
            // Get the authenticated API client
            $apiClient = $request->input('api_client');

            // Generate unique request ID
            $requestId = 'req_' . time() . '_' . Str::random(16);

            Log::info('API Payment Request Received', [
                'request_id' => $requestId,
                'client_id' => $apiClient->id,
                'client_name' => $apiClient->name,
                'ip' => $request->ip()
            ]);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'external_order_id' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|in:USD',
                'customer' => 'required|array',
                'customer.email' => 'required|email',
                'customer.first_name' => 'required|string|max:255',
                'customer.last_name' => 'required|string|max:255',
                'customer.phone' => 'nullable|string|max:50',
                'billing_address' => 'required|array',
                'billing_address.address1' => 'required|string',
                'billing_address.city' => 'required|string',
                'billing_address.country' => 'required|string',
                'billing_address.zip' => 'required|string',
                'products' => 'required|array|min:1',
                'products.*.name' => 'required|string',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.price' => 'required|numeric|min:0',
                'return_url' => 'required|url',
                'cancel_url' => 'required|url',
                'webhook_url' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                Log::warning('API Payment Validation Failed', [
                    'request_id' => $requestId,
                    'client_id' => $apiClient->id,
                    'errors' => $validator->errors()->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'request_id' => $requestId
                ], 422);
            }

            // Check for duplicate external order ID
            $existingTransaction = ApiTransaction::where('api_client_id', $apiClient->id)
                ->where('external_order_id', $request->external_order_id)
                ->first();

            if ($existingTransaction) {
                Log::warning('Duplicate external order ID', [
                    'request_id' => $requestId,
                    'external_order_id' => $request->external_order_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate order ID. This order has already been processed.',
                    'request_id' => $requestId,
                    'existing_transaction' => [
                        'id' => $existingTransaction->id,
                        'status' => $existingTransaction->status,
                        'payment_status' => $existingTransaction->payment_status
                    ]
                ], 409);
            }

            // Create API transaction record
            $apiTransaction = ApiTransaction::create([
                'api_client_id' => $apiClient->id,
                'external_order_id' => $request->external_order_id,
                'request_id' => $requestId,
                'status' => ApiTransaction::STATUS_PENDING,
                'request_data' => json_encode($request->except(['api_client'])),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'request_ip' => $request->ip(),
                'customer_info' => json_encode($request->customer),
                'product_info' => json_encode($request->products),
            ]);

            // Mark as processing
            $apiTransaction->markAsProcessing();

            Log::info('Starting Dodopayments processing', [
                'request_id' => $requestId,
                'transaction_id' => $apiTransaction->id,
                'amount' => $request->amount,
                'currency' => $request->currency
            ]);

            // Prevent PHP script timeout during payment processing
            // Allow enough time for Dodopayments call (90s) + processing (30s)
            set_time_limit(180); // 3 minutes
            
            Log::info('PHP execution time limit set to 180 seconds for Dodopayments call');

            // Register shutdown function to catch fatal errors or timeouts
            $shutdownHandlerRegistered = false;
            register_shutdown_function(function() use ($requestId, $apiTransaction, &$shutdownHandlerRegistered) {
                $error = error_get_last();
                
                // Check if this is a fatal error or timeout
                if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                    Log::error('CRITICAL: PHP Fatal Error during Dodopayments call', [
                        'request_id' => $requestId,
                        'transaction_id' => $apiTransaction->id ?? null,
                        'error_type' => $error['type'],
                        'error_message' => $error['message'],
                        'error_file' => $error['file'],
                        'error_line' => $error['line']
                    ]);
                    
                    // Try to mark transaction as failed
                    try {
                        if ($apiTransaction && method_exists($apiTransaction, 'markAsFailed')) {
                            $apiTransaction->markAsFailed('PHP Fatal Error: ' . $error['message']);
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            });
            $shutdownHandlerRegistered = true;

            // Process the payment via Dodopayments
            Log::info('About to call processDodoPayment method', [
                'request_id' => $requestId,
                'transaction_id' => $apiTransaction->id,
                'timestamp' => Carbon::now()->toIso8601String()
            ]);
            
            $paymentResult = $this->processDodoPayment($request, $apiClient, $apiTransaction);

            Log::info('processDodoPayment method returned', [
                'request_id' => $requestId,
                'transaction_id' => $apiTransaction->id,
                'result_is_array' => is_array($paymentResult),
                'has_success_key' => isset($paymentResult['success']),
                'timestamp' => Carbon::now()->toIso8601String()
            ]);
            
            Log::info('Dodopayments processing completed', [
                'request_id' => $requestId,
                'transaction_id' => $apiTransaction->id,
                'success' => $paymentResult['success'] ?? 'not_set',
                'payment_id' => $paymentResult['payment_id'] ?? null
            ]);

            if (!$paymentResult['success']) {
                $apiTransaction->markAsFailed($paymentResult['message']);
                
                $errorType = isset($paymentResult['error_type']) ? $paymentResult['error_type'] : 'unknown';
                
                // Determine appropriate HTTP status code based on error type
                $httpStatusCode = 500; // Default to 500 Internal Server Error
                $errorCode = 'PROCESSING_ERROR';
                
                if ($errorType === 'timeout' || stripos($paymentResult['message'], 'timeout') !== false) {
                    $httpStatusCode = 504; // Gateway Timeout
                    $errorCode = 'GATEWAY_TIMEOUT';
                } elseif ($errorType === 'connection' || stripos($paymentResult['message'], 'connection') !== false) {
                    $httpStatusCode = 503; // Service Unavailable
                    $errorCode = 'SERVICE_UNAVAILABLE';
                } elseif ($errorType === 'http_error') {
                    $httpStatusCode = 502; // Bad Gateway
                    $errorCode = 'BAD_GATEWAY';
                }
                
                Log::error('CRITICAL: Dodopayments failed - Sending error response to API client (SenyTV) NOW', [
                    'request_id' => $requestId,
                    'transaction_id' => $apiTransaction->id,
                    'error_message' => $paymentResult['message'],
                    'error_type' => $errorType,
                    'error_code' => $errorCode,
                    'http_status_code' => $httpStatusCode,
                    'timestamp' => Carbon::now()->toIso8601String()
                ]);
                
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'],
                    'error_code' => $errorCode,
                    'error_type' => $errorType,
                    'request_id' => $requestId,
                    'transaction_id' => $apiTransaction->id,
                    'timestamp' => Carbon::now()->toIso8601String()
                ], $httpStatusCode);
                
                Log::error('RESPONSE OBJECT CREATED - About to return to API client', [
                    'request_id' => $requestId,
                    'status_code' => $httpStatusCode,
                    'response_created' => true
                ]);
                
                return $errorResponse;
            }

            // Create the order in the system
            $order = $this->createOrderFromApi($request, $apiClient, $apiTransaction, $paymentResult);

            // Update transaction with order ID
            $apiTransaction->markAsCompleted($order->id, $paymentResult['payment_id'] ?? null);
            $apiTransaction->updatePaymentStatus(ApiTransaction::PAYMENT_PENDING);

            // Update response data
            $apiTransaction->update([
                'response_data' => json_encode([
                    'order_id' => $order->id,
                    'transaction_number' => $order->transaction_number,
                    'payment_url' => $paymentResult['payment_url'] ?? null,
                    'payment_id' => $paymentResult['payment_id'] ?? null
                ])
            ]);

            Log::info('API Payment Processed Successfully', [
                'request_id' => $requestId,
                'transaction_id' => $apiTransaction->id,
                'order_id' => $order->id
            ]);

            $responseData = [
                'success' => true,
                'message' => 'Order created successfully',
                'request_id' => $requestId,
                'data' => [
                    'transaction_id' => $apiTransaction->id,
                    'order_id' => $order->id,
                    'transaction_number' => $order->transaction_number,
                    'payment_url' => $paymentResult['payment_url'] ?? null,
                    'payment_id' => $paymentResult['payment_id'] ?? null,
                    'amount' => $order->cart ? PriceHelper::OrderTotal($order, 'total') : $request->amount,
                    'currency' => $request->currency,
                    'status' => 'pending_payment'
                ]
            ];

            Log::info('Sending success response to client', [
                'request_id' => $requestId,
                'transaction_id' => $apiTransaction->id,
                'order_id' => $order->id,
                'payment_url_included' => isset($responseData['data']['payment_url']),
                'response_size_bytes' => strlen(json_encode($responseData))
            ]);

            $successResponse = response()->json($responseData, 201);
            
            Log::info('SUCCESS RESPONSE OBJECT CREATED - About to return to API client', [
                'request_id' => $requestId,
                'order_id' => $order->id,
                'status_code' => 201,
                'response_created' => true
            ]);

            return $successResponse;

        } catch (\Exception $e) {
            $isTimeout = (stripos($e->getMessage(), 'timeout') !== false) || 
                         (stripos($e->getMessage(), 'timed out') !== false);
            
            Log::error('API Payment Error', [
                'request_id' => $requestId ?? 'unknown',
                'transaction_id' => isset($apiTransaction) ? $apiTransaction->id : null,
                'message' => $e->getMessage(),
                'is_timeout' => $isTimeout,
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($apiTransaction)) {
                $apiTransaction->markAsFailed($e->getMessage());
            }

            Log::error('SENDING ERROR RESPONSE TO API CLIENT (SenyTV) - createOrder method exception', [
                'request_id' => $requestId ?? 'unknown',
                'transaction_id' => isset($apiTransaction) ? $apiTransaction->id : null,
                'error_type' => $isTimeout ? 'timeout' : 'exception',
                'error_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'response_code' => 500,
                'timestamp' => Carbon::now()->toIso8601String()
            ]);

            $exceptionResponse = response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => $isTimeout ? 'timeout' : 'exception',
                'request_id' => $requestId ?? null,
                'transaction_id' => isset($apiTransaction) ? $apiTransaction->id : null
            ], 500);
            
            Log::error('EXCEPTION RESPONSE OBJECT CREATED - About to return to API client', [
                'request_id' => $requestId ?? 'unknown',
                'status_code' => 500,
                'response_created' => true,
                'exception_class' => get_class($e)
            ]);

            return $exceptionResponse;
        }
    }

    /**
     * Process payment via Dodopayments
     */
    protected function processDodoPayment($request, $apiClient, $apiTransaction)
    {
        try {
            // Get DodoPayments settings
            $dodoSettings = PaymentSetting::whereUniqueKeyword('dodopayments')->first();
            if (!$dodoSettings) {
                throw new \Exception('DodoPayments settings not found');
            }

            $dodoData = $dodoSettings->convertJsonData();
            $apiKey = $dodoData['api_key'] ?? null;

            if (empty($apiKey)) {
                throw new \Exception('DodoPayments API key is not configured');
            }

            // Initialize Dodopayments client
            $client = new Client($apiKey);

            // Prepare billing address
            $billing = [
                'address_line_1' => $request->input('billing_address.address1'),
                'address_line_2' => $request->input('billing_address.address2', ''),
                'city' => $request->input('billing_address.city'),
                'country' => $request->input('billing_address.country'),
                'postal_code' => $request->input('billing_address.zip'),
                'state' => $request->input('billing_address.state', ''),
            ];

            // Prepare customer
            $customer = [
                'email' => $request->input('customer.email'),
                'name' => $request->input('customer.first_name') . ' ' . $request->input('customer.last_name'),
                'phone' => $request->input('customer.phone', ''),
            ];

            // Prepare products
            $productCart = [];
            foreach ($request->products as $product) {
                $productCart[] = [
                    'currency' => $request->currency,
                    'name' => $product['name'],
                    'quantity' => $product['quantity'],
                    'price' => (float)$product['price']
                ];
            }

            // Prepare metadata (Dodopayments expects strings)
            $metadata = [
                'api_client_id' => (string) $apiClient->id,
                'api_client_name' => (string) $apiClient->name,
                'api_transaction_id' => (string) $apiTransaction->id,
                'external_order_id' => (string) $request->external_order_id,
                'request_id' => (string) $apiTransaction->request_id,
            ];

            // Return URL with transaction info
            $returnURL = $request->return_url . 
                (strpos($request->return_url, '?') !== false ? '&' : '?') . 
                'request_id=' . $apiTransaction->request_id . 
                '&external_order_id=' . $request->external_order_id;

            Log::info('Preparing Dodopayments API call', [
                'transaction_id' => $apiTransaction->id,
                'request_id' => $apiTransaction->request_id,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'customer_email' => $customer['email'],
                'products_count' => count($productCart),
                'timeout_seconds' => 90
            ]);

            // Create payment
            $requestOptions = new \DodoPayments\RequestOptions();
            $requestOptions->timeout = 90; // Increased timeout from 30 to 90 seconds

            $apiCallStart = microtime(true);
            
            Log::info('Calling Dodopayments API now', [
                'transaction_id' => $apiTransaction->id,
                'request_id' => $apiTransaction->request_id,
                'timestamp' => Carbon::now()->toIso8601String(),
                'timeout_setting' => 90
            ]);

            // PRIMARY PATH: Direct HTTP call to Dodopayments (more reliable than SDK)
            try {
                $baseUrl = str_starts_with($apiKey, 'test_') ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';

                // Sanitize payload values to avoid nulls (Dodopayments rejects null for strings)
                $billingStreet = (string) ($request->input('billing_address.address1') ?? '');
                $billingCity = (string) ($request->input('billing_address.city') ?? '');
                $billingState = (string) ($request->input('billing_address.state') ?? '');
                $billingCountry = (string) ($request->input('billing_address.country') ?? '');
                $billingZip = (string) ($request->input('billing_address.zip') ?? '');
                $customerEmail = (string) ($request->input('customer.email') ?? '');
                $customerFirst = (string) ($request->input('customer.first_name') ?? '');
                $customerLast = (string) ($request->input('customer.last_name') ?? '');
                $customerName = trim($customerFirst . ' ' . $customerLast);
                if ($customerName === '') { $customerName = $customerEmail; }

                // Build product cart for HTTP payload
                $httpProductCart = [];
                foreach ($request->products as $index => $product) {
                    $httpProductCart[] = [
                        'product_id' => (string) ($product['id'] ?? 'api_product_' . $index),
                        'name' => (string) ($product['name'] ?? 'Product'),
                        'quantity' => (int) ($product['quantity'] ?? 1),
                        'price' => (float) ($product['price'] ?? 0)
                    ];
                }

                $httpPayload = [
                    'payment_link' => true,
                    'amount' => (int) round(((float) $request->amount) * 100),
                    'currency' => $request->currency,
                    'product_cart' => $httpProductCart,
                    'billing' => [
                        'street' => $billingStreet,
                        'city' => $billingCity,
                        'state' => $billingState,
                        'country' => $billingCountry,
                        'zipcode' => $billingZip
                    ],
                    'customer' => [
                        'email' => $customerEmail,
                        'name' => $customerName
                    ],
                    'return_url' => $returnURL,
                    'metadata' => $metadata
                ];

                $httpStart = microtime(true);
                Log::info('Calling Dodopayments HTTP API now', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'endpoint' => $baseUrl . '/payments'
                ]);

                $httpResponse = Http::timeout(90)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($baseUrl . '/payments', $httpPayload);

                $httpDuration = round((microtime(true) - $httpStart) * 1000, 2);

                Log::info('Dodopayments HTTP API responded', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'status' => $httpResponse->status(),
                    'duration_ms' => $httpDuration,
                    'response_preview' => substr($httpResponse->body(), 0, 200)
                ]);

                if ($httpResponse->successful()) {
                    $json = $httpResponse->json();
                    $paymentId = $json['payment_id'] ?? $json['id'] ?? $json['checkout_session_id'] ?? null;
                    $paymentUrl = $json['payment_url'] ?? $json['checkout_url'] ?? null;

                    if ($paymentId) {
                        Log::info('Dodopayments HTTP API created payment successfully', [
                            'transaction_id' => $apiTransaction->id,
                            'payment_id' => $paymentId,
                            'has_payment_url' => !empty($paymentUrl)
                        ]);

                        return [
                            'success' => true,
                            'payment_id' => $paymentId,
                            'payment_url' => $paymentUrl
                        ];
                    }
                }

                Log::warning('Dodopayments HTTP API did not return a valid payment, falling back to SDK', [
                    'transaction_id' => $apiTransaction->id,
                    'status' => $httpResponse->status()
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                Log::error('Dodopayments HTTP API connection timeout - falling back to SDK', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'error' => $ce->getMessage()
                ]);
            } catch (\Throwable $te) {
                Log::error('Dodopayments HTTP API threw exception - falling back to SDK', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'error' => $te->getMessage(),
                    'class' => get_class($te)
                ]);
            }

            // SECONDARY PATH: Wrap SDK call in try-catch for granular error handling
            $payment = null;
            $timedOut = false;
            
            try {
                // Set a hard limit - if SDK doesn't respect timeout, this will catch it
                $maxWaitTime = 95; // 95 seconds (5 seconds buffer after SDK timeout)
                $startTime = time();
                
                Log::info('Attempting Dodopayments SDK call with timeout protection', [
                    'max_wait_seconds' => $maxWaitTime,
                    'transaction_id' => $apiTransaction->id
                ]);
                
                $payment = $client->payments->create(
                    $billing,
                    $customer,
                    $productCart,
                    null, // allowedPaymentMethodTypes
                    null, // billingCurrency
                    null, // discountCode
                    $metadata,
                    true, // paymentLink
                    $returnURL,
                    null, // showSavedPaymentMethods
                    null, // taxID
                    $requestOptions
                );
                
                // SDK call returned - log immediately
                Log::info('Dodopayments SDK payments->create() returned', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'payment_object_type' => gettype($payment),
                    'payment_is_null' => is_null($payment),
                    'timestamp' => Carbon::now()->toIso8601String()
                ]);
                
                // Check if we exceeded our time limit (SDK might not have thrown exception)
                $elapsedTime = time() - $startTime;
                if ($elapsedTime >= $maxWaitTime) {
                    $timedOut = true;
                    Log::error('Dodopayments SDK call completed but took too long', [
                        'elapsed_seconds' => $elapsedTime,
                        'max_wait_seconds' => $maxWaitTime,
                        'transaction_id' => $apiTransaction->id
                    ]);
                    throw new \Exception('Dodopayments API call exceeded maximum wait time (' . $elapsedTime . 's)');
                }

                $apiCallDuration = round((microtime(true) - $apiCallStart) * 1000, 2); // milliseconds

                Log::info('Dodopayments API responded successfully', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'duration_ms' => $apiCallDuration,
                    'has_payment_id' => isset($payment->payment_id),
                    'has_payment_url' => isset($payment->payment_url),
                    'payment_id' => $payment->payment_id ?? null,
                    'payment_url' => $payment->payment_url ?? null,
                    'timestamp' => Carbon::now()->toIso8601String()
                ]);

                if (!$payment || !isset($payment->payment_id)) {
                    Log::error('Dodopayments API returned invalid response', [
                        'transaction_id' => $apiTransaction->id,
                        'request_id' => $apiTransaction->request_id,
                        'payment_object' => $payment ? 'exists' : 'null',
                        'has_payment_id' => isset($payment->payment_id)
                    ]);
                    throw new \Exception('Failed to create payment with Dodopayments - no payment ID returned');
                }

            } catch (\Throwable $e) {
                // Catch ALL exceptions and errors (including fatal errors)
                $apiCallDuration = round((microtime(true) - $apiCallStart) * 1000, 2);
                $exceptionClass = get_class($e);
                
                Log::error('CRITICAL: Dodopayments SDK call threw exception or error', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'exception_class' => $exceptionClass,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'duration_ms' => $apiCallDuration,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'timestamp' => Carbon::now()->toIso8601String()
                ]);
                
                // Determine error type for better error codes
                $isTimeout = stripos($e->getMessage(), 'timeout') !== false || 
                             stripos($e->getMessage(), 'timed out') !== false ||
                             stripos($exceptionClass, 'Timeout') !== false;
                
                $isConnection = stripos($e->getMessage(), 'connection') !== false ||
                                stripos($e->getMessage(), 'connect') !== false ||
                                stripos($exceptionClass, 'Connect') !== false;
                
                if ($isTimeout) {
                    Log::error('Detected as TIMEOUT error', ['transaction_id' => $apiTransaction->id]);
                    throw new \Exception('Dodopayments API connection timeout after ' . $apiCallDuration . 'ms. The payment gateway did not respond in time.');
                } elseif ($isConnection) {
                    Log::error('Detected as CONNECTION error', ['transaction_id' => $apiTransaction->id]);
                    throw new \Exception('Dodopayments API connection error: ' . $e->getMessage());
                } else {
                    Log::error('Detected as GENERAL error', ['transaction_id' => $apiTransaction->id]);
                    throw new \Exception('Dodopayments API error: ' . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'payment_id' => $payment->payment_id,
                'payment_url' => $payment->payment_url ?? null,
            ];

        } catch (\Exception $e) {
            $isTimeout = (stripos($e->getMessage(), 'timeout') !== false) || 
                         (stripos($e->getMessage(), 'timed out') !== false) ||
                         (stripos($e->getMessage(), 'connection') !== false);
            
            Log::error('Dodopayments processDodoPayment method failed - ENSURING RESPONSE TO API CLIENT', [
                'client_id' => $apiClient->id,
                'transaction_id' => $apiTransaction->id,
                'request_id' => $apiTransaction->request_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'is_timeout' => $isTimeout,
                'exception_class' => get_class($e),
                'will_return_error_to_client' => true,
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: attempt direct HTTP call to DodoPayments API to create payment link
            try {
                $dodoSettings = PaymentSetting::whereUniqueKeyword('dodopayments')->first();
                $dodoData = $dodoSettings ? $dodoSettings->convertJsonData() : [];
                $apiKey = $dodoData['api_key'] ?? null;
                if (!$apiKey) {
                    throw new \Exception('DodoPayments API key missing for HTTP fallback');
                }

                $baseUrl = str_starts_with($apiKey, 'test_') ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';

                $amountCents = (int) round(((float) $request->amount) * 100);

                $payload = [
                    'payment_link' => true,
                    'amount' => $amountCents,
                    'currency' => $request->currency,
                    'billing' => [
                        'street' => $request->input('billing_address.address1'),
                        'city' => $request->input('billing_address.city'),
                        'state' => $request->input('billing_address.state', ''),
                        'country' => $request->input('billing_address.country'),
                        'zipcode' => $request->input('billing_address.zip'),
                    ],
                    'customer' => [
                        'email' => $request->input('customer.email'),
                        'name' => $request->input('customer.first_name') . ' ' . $request->input('customer.last_name')
                    ],
                    'return_url' => $returnURL ?? ($request->return_url ?? ''),
                    'metadata' => [
                        'api_client_id' => $apiClient->id,
                        'api_transaction_id' => $apiTransaction->id,
                        'external_order_id' => $request->external_order_id,
                        'request_id' => $apiTransaction->request_id,
                    ]
                ];

                Log::warning('Dodopayments SDK failed - attempting direct HTTP fallback', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'base_url' => $baseUrl,
                    'endpoint' => $baseUrl . '/payments',
                    'amount_cents' => $amountCents
                ]);

                $httpStart = microtime(true);
                $response = Http::timeout(90)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($baseUrl . '/payments', $payload);

                $httpDuration = round((microtime(true) - $httpStart) * 1000, 2);

                Log::info('Dodopayments HTTP fallback response', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'status' => $response->status(),
                    'duration_ms' => $httpDuration,
                    'response_preview' => substr($response->body(), 0, 300)
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $paymentId = $responseData['payment_id'] ?? $responseData['id'] ?? $responseData['checkout_session_id'] ?? null;
                    $paymentUrl = $responseData['payment_url'] ?? $responseData['checkout_url'] ?? null;

                    if ($paymentId) {
                        Log::info('Dodopayments HTTP fallback created payment successfully', [
                            'transaction_id' => $apiTransaction->id,
                            'payment_id' => $paymentId,
                            'has_payment_url' => !empty($paymentUrl)
                        ]);

                        return [
                            'success' => true,
                            'payment_id' => $paymentId,
                            'payment_url' => $paymentUrl
                        ];
                    }
                }

                // If we reach here, fallback failed
                Log::error('Dodopayments HTTP fallback failed', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment processing failed: Dodopayments HTTP fallback failed with status ' . $response->status(),
                    'error_type' => 'http_error'
                ];

            } catch (\Illuminate\Http\Client\ConnectionException $he) {
                Log::error('Dodopayments HTTP fallback connection timeout', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'error' => $he->getMessage()
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment processing failed: Dodopayments HTTP fallback timeout',
                    'error_type' => 'timeout'
                ];
            } catch (\Throwable $he) {
                Log::error('Dodopayments HTTP fallback threw exception', [
                    'transaction_id' => $apiTransaction->id,
                    'request_id' => $apiTransaction->request_id,
                    'error' => $he->getMessage(),
                    'class' => get_class($he)
                ]);

                return [
                    'success' => false,
                    'message' => 'Payment processing failed: Dodopayments HTTP fallback error: ' . $he->getMessage(),
                    'error_type' => 'error'
                ];
            }
        }
    }

    /**
     * Create order from API request
     */
    protected function createOrderFromApi($request, $apiClient, $apiTransaction, $paymentResult)
    {
        $setting = Setting::first();

        // Prepare cart data
        $cart = [];
        foreach ($request->products as $index => $product) {
            $cart['api_product_' . $index] = [
                'name' => $product['name'],
                'qty' => $product['quantity'],
                'price' => $product['price'],
                'main_price' => $product['price'],
                'attribute_price' => 0,
                'item_type' => 'api',
            ];
        }

        // Prepare billing info
        $billingInfo = array_merge(
            $request->billing_address,
            [
                'bill_first_name' => $request->input('customer.first_name'),
                'bill_last_name' => $request->input('customer.last_name'),
                'bill_email' => $request->input('customer.email'),
                'bill_phone' => $request->input('customer.phone', ''),
            ]
        );

        // Create order
        $orderData = [
            'user_id' => 0, // API orders are not linked to users
            'api_client_id' => $apiClient->id,
            'external_order_id' => $request->external_order_id,
            'cart' => json_encode($cart),
            'shipping' => json_encode(null),
            'discount' => json_encode([]),
            'payment_method' => 'DodoPayments',
            'txnid' => $paymentResult['payment_id'] ?? null,
            'transaction_number' => Str::random(10),
            'order_status' => 'Pending',
            'payment_status' => 'Unpaid',
            'shipping_info' => json_encode($request->input('shipping_address', [])),
            'billing_info' => json_encode($billingInfo),
            'currency_sign' => '$',
            'currency_value' => 1,
            'tax' => 0,
            'state_price' => 0,
            'state' => null,
        ];

        $order = Order::create($orderData);

        // Generate proper transaction number
        $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
        $order->transaction_number = $new_txn;
        $order->save();

        // Create track order
        TrackOrder::create([
            'title' => 'Pending',
            'order_id' => $order->id,
        ]);

        // Create notification
        Notification::create([
            'order_id' => $order->id
        ]);

        Log::info('Order created from API', [
            'order_id' => $order->id,
            'transaction_number' => $order->transaction_number,
            'api_client_id' => $apiClient->id
        ]);

        return $order;
    }

    /**
     * Get order status by request ID
     * 
     * @param Request $request
     * @param string $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderStatus(Request $request, $requestId)
    {
        try {
            $apiClient = $request->input('api_client');

            $transaction = ApiTransaction::where('request_id', $requestId)
                ->where('api_client_id', $apiClient->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            $order = $transaction->order;

            return response()->json([
                'success' => true,
                'data' => [
                    'request_id' => $transaction->request_id,
                    'transaction_id' => $transaction->id,
                    'external_order_id' => $transaction->external_order_id,
                    'status' => $transaction->status,
                    'payment_status' => $transaction->payment_status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'order' => $order ? [
                        'id' => $order->id,
                        'transaction_number' => $order->transaction_number,
                        'order_status' => $order->order_status,
                        'payment_status' => $order->payment_status,
                        'created_at' => $order->created_at->toIso8601String(),
                    ] : null,
                    'created_at' => $transaction->created_at->toIso8601String(),
                    'updated_at' => $transaction->updated_at->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Order Status Error', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching order status'
            ], 500);
        }
    }

    /**
     * List all transactions for the authenticated API client
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listTransactions(Request $request)
    {
        try {
            $apiClient = $request->input('api_client');
            
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $paymentStatus = $request->input('payment_status');

            $query = ApiTransaction::where('api_client_id', $apiClient->id)
                ->with('order');

            if ($status) {
                $query->where('status', $status);
            }

            if ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            }

            $transactions = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            Log::error('List Transactions Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching transactions'
            ], 500);
        }
    }
}

