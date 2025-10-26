<?php
/**
 * Sample Integration for External Websites
 * 
 * This file demonstrates how to integrate with the SenyPro Payment Gateway API
 * from an external website (e.g., senytv.com)
 * 
 * Requirements:
 * - PHP 7.4 or higher
 * - cURL extension enabled
 * - API credentials from senypro.com
 */

class SenyProPaymentGateway
{
    private $apiKey;
    private $apiSecret;
    private $apiBaseUrl;

    /**
     * Constructor
     * 
     * @param string $apiKey Your API Key from senypro.com
     * @param string $apiSecret Your API Secret from senypro.com
     * @param string $apiBaseUrl Base URL (default: https://senypro.com/api/v1)
     */
    public function __construct($apiKey, $apiSecret, $apiBaseUrl = 'https://senypro.com/api/v1')
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
    }

    /**
     * Create a new order and initiate payment
     * 
     * @param array $orderData Order information
     * @return array Response from API
     * @throws Exception on error
     */
    public function createOrder($orderData)
    {
        // Validate required fields
        $this->validateOrderData($orderData);

        // Send request
        $response = $this->sendRequest('POST', '/orders', $orderData);

        return $response;
    }

    /**
     * Get order status by request ID
     * 
     * @param string $requestId The request ID returned when order was created
     * @return array Response from API
     * @throws Exception on error
     */
    public function getOrderStatus($requestId)
    {
        return $this->sendRequest('GET', '/orders/' . $requestId);
    }

    /**
     * List all transactions
     * 
     * @param array $filters Optional filters (per_page, status, payment_status)
     * @return array Response from API
     * @throws Exception on error
     */
    public function listTransactions($filters = [])
    {
        $query = http_build_query($filters);
        $endpoint = '/transactions' . ($query ? '?' . $query : '');
        
        return $this->sendRequest('GET', $endpoint);
    }

    /**
     * Validate order data
     * 
     * @param array $orderData
     * @throws Exception if validation fails
     */
    private function validateOrderData($orderData)
    {
        $required = [
            'external_order_id',
            'amount',
            'currency',
            'customer',
            'billing_address',
            'products',
            'return_url',
            'cancel_url'
        ];

        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Validate customer fields
        $customerRequired = ['email', 'first_name', 'last_name'];
        foreach ($customerRequired as $field) {
            if (empty($orderData['customer'][$field])) {
                throw new Exception("Missing required customer field: {$field}");
            }
        }

        // Validate billing address
        $addressRequired = ['address1', 'city', 'country', 'zip'];
        foreach ($addressRequired as $field) {
            if (empty($orderData['billing_address'][$field])) {
                throw new Exception("Missing required billing address field: {$field}");
            }
        }

        // Validate products
        if (!is_array($orderData['products']) || empty($orderData['products'])) {
            throw new Exception("Products must be a non-empty array");
        }
    }

    /**
     * Send HTTP request to API
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array $data Request data (for POST/PUT)
     * @return array Response from API
     * @throws Exception on error
     */
    private function sendRequest($method, $endpoint, $data = null)
    {
        $url = $this->apiBaseUrl . $endpoint;
        
        $ch = curl_init($url);
        
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey,
            'X-API-Secret: ' . $this->apiSecret
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $result['message'] ?? 'Unknown error';
            throw new Exception("API error ({$httpCode}): {$errorMessage}");
        }

        return $result;
    }
}

// =====================================================
// USAGE EXAMPLE
// =====================================================

// Initialize the gateway
$gateway = new SenyProPaymentGateway(
    'sk_your_api_key_here',
    'your_api_secret_here'
);

// Example 1: Create an order
try {
    $orderData = [
        'external_order_id' => 'TV-ORDER-' . time(),
        'amount' => 99.99,
        'currency' => 'USD',
        'customer' => [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890'
        ],
        'billing_address' => [
            'address1' => '123 Main St',
            'address2' => 'Apt 4B',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'zip' => '10001'
        ],
        'shipping_address' => [
            'address1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'zip' => '10001'
        ],
        'products' => [
            [
                'name' => 'Premium Subscription - 1 Month',
                'quantity' => 1,
                'price' => 99.99
            ]
        ],
        'return_url' => 'https://senytv.com/payment/success',
        'cancel_url' => 'https://senytv.com/payment/cancel',
        'webhook_url' => 'https://senytv.com/webhooks/payment'
    ];

    $response = $gateway->createOrder($orderData);

    if ($response['success']) {
        // Store request_id for later reference
        $requestId = $response['request_id'];
        
        // Redirect customer to payment URL
        header('Location: ' . $response['data']['payment_url']);
        exit;
    } else {
        echo "Error: " . $response['message'];
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

// Example 2: Check order status
try {
    $requestId = 'req_1635427890_abc123def456'; // From previous order creation
    
    $response = $gateway->getOrderStatus($requestId);
    
    if ($response['success']) {
        $status = $response['data']['status'];
        $paymentStatus = $response['data']['payment_status'];
        
        echo "Order Status: {$status}\n";
        echo "Payment Status: {$paymentStatus}\n";
        
        if ($paymentStatus === 'Paid') {
            echo "Payment completed successfully!";
        }
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

// Example 3: List transactions
try {
    $response = $gateway->listTransactions([
        'per_page' => 10,
        'status' => 'completed'
    ]);

    if ($response['success']) {
        foreach ($response['data']['data'] as $transaction) {
            echo "Transaction: {$transaction['external_order_id']} - {$transaction['payment_status']}\n";
        }
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}

// =====================================================
// WORDPRESS INTEGRATION EXAMPLE
// =====================================================

/**
 * Example: WordPress WooCommerce Integration
 * 
 * Add this to your theme's functions.php or create a custom plugin
 */

// Add custom payment gateway
add_filter('woocommerce_payment_gateways', 'add_senypro_gateway');
function add_senypro_gateway($gateways)
{
    $gateways[] = 'WC_SenyPro_Gateway';
    return $gateways;
}

// Define the gateway class
add_action('plugins_loaded', 'init_senypro_gateway');
function init_senypro_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_SenyPro_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'senypro';
            $this->method_title = 'SenyPro Payment Gateway';
            $this->method_description = 'Pay via SenyPro using Dodopayments';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->api_key = $this->get_option('api_key');
            $this->api_secret = $this->get_option('api_secret');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_senypro_gateway', array($this, 'webhook'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable SenyPro Payment Gateway',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'default' => 'Credit Card / Debit Card',
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'default' => 'Pay securely with your credit or debit card.',
                ),
                'api_key' => array(
                    'title' => 'API Key',
                    'type' => 'text',
                ),
                'api_secret' => array(
                    'title' => 'API Secret',
                    'type' => 'password',
                )
            );
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            try {
                $gateway = new SenyProPaymentGateway(
                    $this->api_key,
                    $this->api_secret
                );

                $orderData = [
                    'external_order_id' => 'WC-' . $order_id,
                    'amount' => $order->get_total(),
                    'currency' => 'USD',
                    'customer' => [
                        'email' => $order->get_billing_email(),
                        'first_name' => $order->get_billing_first_name(),
                        'last_name' => $order->get_billing_last_name(),
                        'phone' => $order->get_billing_phone()
                    ],
                    'billing_address' => [
                        'address1' => $order->get_billing_address_1(),
                        'address2' => $order->get_billing_address_2(),
                        'city' => $order->get_billing_city(),
                        'state' => $order->get_billing_state(),
                        'country' => $order->get_billing_country(),
                        'zip' => $order->get_billing_postcode()
                    ],
                    'products' => [],
                    'return_url' => $this->get_return_url($order),
                    'cancel_url' => $order->get_cancel_order_url(),
                ];

                // Add products
                foreach ($order->get_items() as $item) {
                    $orderData['products'][] = [
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'price' => $item->get_total() / $item->get_quantity()
                    ];
                }

                $response = $gateway->createOrder($orderData);

                if ($response['success']) {
                    // Store request ID
                    update_post_meta($order_id, '_senypro_request_id', $response['request_id']);
                    
                    // Return redirect
                    return array(
                        'result' => 'success',
                        'redirect' => $response['data']['payment_url']
                    );
                } else {
                    wc_add_notice($response['message'], 'error');
                    return array('result' => 'fail');
                }

            } catch (Exception $e) {
                wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
                return array('result' => 'fail');
            }
        }
    }
}

