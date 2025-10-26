# Multi-Website Payment Gateway API Integration Guide

## Overview

This system allows external websites (like senytv.com) to send order/payment requests to senypro.com via API. The platform processes payments through Dodopayments while tracking the originating website.

## Table of Contents

1. [Setup](#setup)
2. [API Authentication](#api-authentication)
3. [API Endpoints](#api-endpoints)
4. [Request/Response Examples](#requestresponse-examples)
5. [Error Handling](#error-handling)
6. [Admin Dashboard](#admin-dashboard)
7. [Security](#security)
8. [Testing](#testing)

---

## Setup

### 1. Run Database Migrations

```bash
cd core
php artisan migrate
```

This will create the following tables:
- `api_clients` - Stores approved websites and their API credentials
- `api_transactions` - Logs all API requests and responses
- Adds `api_client_id` and `external_order_id` to `orders` table

### 2. Register Middleware

The API client authentication middleware has been registered as `api.client` in `core/app/Http/Kernel.php`.

### 3. Create an API Client (Admin)

1. Login to admin panel at `/admin`
2. Navigate to "API Clients" menu
3. Click "Add New Client"
4. Fill in the website details:
   - Name: e.g., "SenyTV"
   - Domain: e.g., "senytv.com"
   - Email: contact@senytv.com
   - Description: (optional)
   - Commission Rate: (optional)
   - Allowed IPs: (optional, comma-separated)
5. Click "Create Client"
6. **IMPORTANT:** Copy the API Key and API Secret immediately - they won't be shown again!
7. Approve the client by clicking "Approve Client" button

---

## API Authentication

All API requests must include authentication headers:

```http
X-API-Key: sk_your_api_key_here
X-API-Secret: your_api_secret_here
Content-Type: application/json
```

### Authentication Flow

1. External website includes API credentials in request headers
2. Middleware validates credentials against database
3. Checks if client is approved and active
4. Verifies IP whitelist (if configured)
5. Allows or denies request

---

## API Endpoints

### Base URL

```
https://senypro.com/api/v1
```

### 1. Create Order/Payment

**Endpoint:** `POST /api/v1/orders`

**Purpose:** Create a new order and initiate payment via Dodopayments

**Request Body:**

```json
{
    "external_order_id": "TV-ORDER-12345",
    "amount": 99.99,
    "currency": "USD",
    "customer": {
        "email": "customer@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "phone": "+1234567890"
    },
    "billing_address": {
        "address1": "123 Main St",
        "address2": "Apt 4B",
        "city": "New York",
        "state": "NY",
        "country": "US",
        "zip": "10001"
    },
    "shipping_address": {
        "address1": "123 Main St",
        "city": "New York",
        "state": "NY",
        "country": "US",
        "zip": "10001"
    },
    "products": [
        {
            "name": "Premium Subscription - 1 Month",
            "quantity": 1,
            "price": 99.99
        }
    ],
    "return_url": "https://senytv.com/payment/success",
    "cancel_url": "https://senytv.com/payment/cancel",
    "webhook_url": "https://senytv.com/webhooks/payment"
}
```

**Response (Success - 201):**

```json
{
    "success": true,
    "message": "Order created successfully",
    "request_id": "req_1635427890_abc123def456",
    "data": {
        "transaction_id": 42,
        "order_id": 156,
        "transaction_number": "ORD-20241025-156",
        "payment_url": "https://checkout.dodopayments.com/pay/xyz789",
        "payment_id": "pay_abc123",
        "amount": 99.99,
        "currency": "USD",
        "status": "pending_payment"
    }
}
```

**Next Steps:**
- Redirect customer to `payment_url` to complete payment
- Customer will be redirected back to your `return_url` after payment
- Poll the order status or wait for webhook notification

### 2. Get Order Status

**Endpoint:** `GET /api/v1/orders/{requestId}`

**Purpose:** Check the status of a previous order

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "request_id": "req_1635427890_abc123def456",
        "transaction_id": 42,
        "external_order_id": "TV-ORDER-12345",
        "status": "completed",
        "payment_status": "Paid",
        "amount": 99.99,
        "currency": "USD",
        "order": {
            "id": 156,
            "transaction_number": "ORD-20241025-156",
            "order_status": "Pending",
            "payment_status": "Paid",
            "created_at": "2024-10-25T10:30:00Z"
        },
        "created_at": "2024-10-25T10:30:00Z",
        "updated_at": "2024-10-25T10:35:00Z"
    }
}
```

### 3. List Transactions

**Endpoint:** `GET /api/v1/transactions`

**Purpose:** List all transactions for your API client

**Query Parameters:**
- `per_page` (optional): Number of results per page (default: 15)
- `status` (optional): Filter by status (pending, processing, completed, failed)
- `payment_status` (optional): Filter by payment status (Paid, Unpaid, Pending, Refunded)

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 42,
                "request_id": "req_1635427890_abc123def456",
                "external_order_id": "TV-ORDER-12345",
                "status": "completed",
                "payment_status": "Paid",
                "amount": 99.99,
                "currency": "USD",
                "created_at": "2024-10-25T10:30:00Z"
            }
        ],
        "total": 1,
        "per_page": 15
    }
}
```

---

## Request/Response Examples

### PHP Example (cURL)

```php
<?php

// Configuration
$apiKey = 'sk_your_api_key_here';
$apiSecret = 'your_api_secret_here';
$apiBaseUrl = 'https://senypro.com/api/v1';

// Prepare order data
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
        'city' => 'New York',
        'state' => 'NY',
        'country' => 'US',
        'zip' => '10001'
    ],
    'products' => [
        [
            'name' => 'Premium Subscription',
            'quantity' => 1,
            'price' => 99.99
        ]
    ],
    'return_url' => 'https://senytv.com/payment/success',
    'cancel_url' => 'https://senytv.com/payment/cancel'
];

// Initialize cURL
$ch = curl_init($apiBaseUrl . '/orders');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey,
    'X-API-Secret: ' . $apiSecret
]);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse response
$result = json_decode($response, true);

if ($httpCode === 201 && $result['success']) {
    // Redirect to payment URL
    header('Location: ' . $result['data']['payment_url']);
    exit;
} else {
    // Handle error
    echo "Error: " . $result['message'];
}
```

### JavaScript Example (Fetch API)

```javascript
const apiKey = 'sk_your_api_key_here';
const apiSecret = 'your_api_secret_here';
const apiBaseUrl = 'https://senypro.com/api/v1';

const orderData = {
    external_order_id: 'TV-ORDER-' + Date.now(),
    amount: 99.99,
    currency: 'USD',
    customer: {
        email: 'customer@example.com',
        first_name: 'John',
        last_name: 'Doe',
        phone: '+1234567890'
    },
    billing_address: {
        address1: '123 Main St',
        city: 'New York',
        state: 'NY',
        country: 'US',
        zip: '10001'
    },
    products: [
        {
            name: 'Premium Subscription',
            quantity: 1,
            price: 99.99
        }
    ],
    return_url: 'https://senytv.com/payment/success',
    cancel_url: 'https://senytv.com/payment/cancel'
};

fetch(apiBaseUrl + '/orders', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey,
        'X-API-Secret': apiSecret
    },
    body: JSON.stringify(orderData)
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Redirect to payment URL
        window.location.href = data.data.payment_url;
    } else {
        console.error('Error:', data.message);
    }
})
.catch(error => console.error('Error:', error));
```

---

## Error Handling

### Error Response Format

```json
{
    "success": false,
    "message": "Error description",
    "error_code": "ERROR_CODE",
    "request_id": "req_xxx",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

### Common Error Codes

| HTTP Code | Error Code | Description |
|-----------|------------|-------------|
| 401 | MISSING_CREDENTIALS | API Key or Secret not provided |
| 401 | INVALID_CREDENTIALS | Invalid API Key or Secret |
| 403 | NOT_APPROVED | API client not approved yet |
| 403 | SUSPENDED | API client suspended |
| 403 | IP_NOT_ALLOWED | Request from unauthorized IP |
| 409 | DUPLICATE_ORDER | External order ID already exists |
| 422 | VALIDATION_ERROR | Request data validation failed |
| 500 | SERVER_ERROR | Internal server error |

---

## Admin Dashboard

### Managing API Clients

**Access:** `/admin/api-clients`

**Features:**
- View all API clients
- Create new API client
- Edit client details
- Approve/Revoke client access
- Activate/Suspend client
- Regenerate API credentials
- View client statistics

### Viewing Transactions

**All Transactions:** `/admin/api-transactions`

**Features:**
- View all API transactions across all clients
- Filter by client, status, payment status
- Search by request ID or external order ID
- View detailed transaction information
- Link to associated orders

**Client Transactions:** `/admin/api-clients/{id}/transactions`

**Features:**
- View transactions for specific client
- Same filtering and search capabilities

---

## Security

### Best Practices

1. **Secure Credential Storage**
   - Store API credentials in environment variables
   - Never commit credentials to version control
   - Use secure storage mechanisms (e.g., AWS Secrets Manager)

2. **IP Whitelisting**
   - Configure allowed IP addresses for production servers
   - Helps prevent unauthorized access even if credentials are compromised

3. **HTTPS Only**
   - Always use HTTPS for API requests
   - Never send credentials over HTTP

4. **Monitor Activity**
   - Regularly review transaction logs
   - Set up alerts for unusual activity
   - Check last used timestamp

5. **Rotate Credentials**
   - Periodically regenerate API credentials
   - Update credentials immediately if compromised

### Logging

All API requests are logged with:
- Request ID (unique identifier)
- Client information
- Request IP address
- Request/response data
- Timestamps
- Success/failure status

Access logs at: `/admin/api-transactions`

---

## Testing

### Test Mode

For development/testing, you can use the API in test mode:

1. Create a test API client
2. Use test Dodopayments credentials
3. Test orders won't process real payments

### Postman Collection

A Postman collection is recommended for testing. Here's a sample request:

```
POST https://senypro.com/api/v1/orders
Headers:
  Content-Type: application/json
  X-API-Key: sk_test_...
  X-API-Secret: test_secret_...
  
Body: (see Request Body example above)
```

---

## Support

For technical support or questions:
- Email: support@senypro.com
- Documentation: https://senypro.com/docs/api
- Admin Panel: https://senypro.com/admin

---

## Changelog

### Version 1.0.0 (2024-10-25)
- Initial release
- Support for order creation via API
- Dodopayments integration
- Transaction tracking
- Admin dashboard for client management

---

## License

© 2024 SenyPro. All rights reserved.

