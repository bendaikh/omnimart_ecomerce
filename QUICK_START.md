# Quick Start Guide - Multi-Website Payment Gateway

## Step 1: Run Migrations (5 minutes)

```bash
cd core
php artisan migrate
php artisan cache:clear
```

This will create:
- `api_clients` table
- `api_transactions` table  
- Add `api_client_id` and `external_order_id` columns to `orders` table

## Step 2: Access Admin Panel (2 minutes)

1. Login to your admin panel at: `https://senypro.com/admin`
2. You should now see a new "API Clients" menu item

## Step 3: Create Your First API Client (5 minutes)

1. Navigate to: **API Clients** → **Add New Client**
2. Fill in the form:
   ```
   Website Name: SenyTV
   Domain: senytv.com
   Email: contact@senytv.com
   Description: SenyTV streaming platform
   Commission Rate: 0 (optional)
   Allowed IPs: (leave empty for now)
   ```
3. Click **"Create Client"**
4. **IMPORTANT:** Copy the API credentials NOW (they won't be shown again!)
   ```
   API Key: sk_xxxxxxxxxxxxxxxxx
   API Secret: yyyyyyyyyyyyyyyy
   ```
5. Click **"Approve Client"** button

## Step 4: Test the API (10 minutes)

### Using Postman or cURL:

**Request:**
```bash
curl -X POST https://senypro.com/api/v1/orders \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "external_order_id": "TEST-001",
    "amount": 99.99,
    "currency": "USD",
    "customer": {
      "email": "test@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+1234567890"
    },
    "billing_address": {
      "address1": "123 Test St",
      "city": "New York",
      "state": "NY",
      "country": "US",
      "zip": "10001"
    },
    "products": [
      {
        "name": "Test Product",
        "quantity": 1,
        "price": 99.99
      }
    ],
    "return_url": "https://senytv.com/success",
    "cancel_url": "https://senytv.com/cancel"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Order created successfully",
  "request_id": "req_1635427890_abc123",
  "data": {
    "transaction_id": 1,
    "order_id": 157,
    "transaction_number": "ORD-20241025-157",
    "payment_url": "https://checkout.dodopayments.com/pay/xyz",
    "payment_id": "pay_abc123",
    "amount": 99.99,
    "currency": "USD",
    "status": "pending_payment"
  }
}
```

## Step 5: Verify in Admin Panel (2 minutes)

1. Go to: **API Transactions** → **All Transactions**
2. You should see your test transaction
3. Click on it to view full details

## Step 6: Integrate with Your Website (varies)

### Option A: Use the PHP Class

Copy `SampleIntegration.php` to your project and use it:

```php
require_once 'SampleIntegration.php';

$gateway = new SenyProPaymentGateway(
    'YOUR_API_KEY',
    'YOUR_API_SECRET'
);

$response = $gateway->createOrder($orderData);

if ($response['success']) {
    header('Location: ' . $response['data']['payment_url']);
    exit;
}
```

### Option B: Direct API Integration

See `API_INTEGRATION_GUIDE.md` for detailed examples in:
- PHP (cURL)
- JavaScript (Fetch API)
- WordPress/WooCommerce

## Common Issues & Solutions

### Issue: "MISSING_CREDENTIALS" error
**Solution:** Make sure you're including both headers:
```
X-API-Key: your_key
X-API-Secret: your_secret
```

### Issue: "NOT_APPROVED" error
**Solution:** Go to admin panel and approve the API client

### Issue: "INVALID_CREDENTIALS" error
**Solution:** Verify your credentials are correct. If lost, regenerate them in admin panel.

### Issue: "Currency Not Supported" error
**Solution:** Currently only USD is supported. Make sure `"currency": "USD"`

## Production Checklist

Before going live:

- [ ] Use production Dodopayments API key
- [ ] Enable HTTPS on all websites
- [ ] Store API credentials securely (environment variables)
- [ ] Configure IP whitelist for production servers
- [ ] Test payment flow end-to-end
- [ ] Set up monitoring for failed transactions
- [ ] Document credentials in secure location
- [ ] Test webhook notifications (if implemented)

## Next Steps

1. **Read Full Documentation:** `API_INTEGRATION_GUIDE.md`
2. **Review Implementation:** `IMPLEMENTATION_SUMMARY.md`
3. **Use Sample Code:** `SampleIntegration.php`
4. **Monitor Activity:** Check admin panel regularly

## Support

- **Documentation:** See `API_INTEGRATION_GUIDE.md`
- **Sample Code:** See `SampleIntegration.php`
- **Implementation Details:** See `IMPLEMENTATION_SUMMARY.md`

## Quick Reference

### Admin URLs
- API Clients: `/admin/api-clients`
- All Transactions: `/admin/api-transactions`

### API Endpoints
- Create Order: `POST /api/v1/orders`
- Get Status: `GET /api/v1/orders/{requestId}`
- List Transactions: `GET /api/v1/transactions`

### Authentication Headers
```
X-API-Key: sk_...
X-API-Secret: ...
Content-Type: application/json
```

---

**Total Setup Time: ~25 minutes**

You're now ready to accept payments from external websites! 🎉

