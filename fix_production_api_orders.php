<?php
/**
 * Fix shipping_info in API orders for PRODUCTION
 * 
 * INSTRUCTIONS FOR PRODUCTION:
 * 1. Upload this file to your production root directory: /home/u770530650/domains/senypro.com/public_html/
 * 2. SSH into your server or use cPanel Terminal
 * 3. Run: cd /home/u770530650/domains/senypro.com/public_html && php fix_production_api_orders.php
 * 4. Delete this file after running
 */

require __DIR__ . '/core/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "===========================================\n";
echo "PRODUCTION: Fixing API orders shipping_info\n";
echo "===========================================\n\n";

// Get all orders that have api_client_id (API orders)
$orders = DB::table('orders')->whereNotNull('api_client_id')->get();
$fixedCount = 0;
$errorCount = 0;

echo "Found " . count($orders) . " API orders to check...\n\n";

foreach ($orders as $order) {
    try {
        $shippingInfo = json_decode($order->shipping_info, true);
        $billingInfo = json_decode($order->billing_info, true);
        
        // Check if shipping_info needs fixing (doesn't have ship_first_name)
        if (!isset($shippingInfo['ship_first_name'])) {
            // Build proper shipping info
            $newShippingInfo = [
                'ship_first_name' => $billingInfo['bill_first_name'] ?? '',
                'ship_last_name' => $billingInfo['bill_last_name'] ?? '',
                'ship_email' => $billingInfo['bill_email'] ?? '',
                'ship_phone' => $billingInfo['bill_phone'] ?? '',
                'ship_address1' => $shippingInfo['address1'] ?? $billingInfo['address1'] ?? '',
                'ship_address2' => $shippingInfo['address2'] ?? $billingInfo['address2'] ?? '',
                'ship_city' => $shippingInfo['city'] ?? $billingInfo['city'] ?? '',
                'ship_state' => $shippingInfo['state'] ?? $billingInfo['state'] ?? '',
                'ship_country' => $shippingInfo['country'] ?? $billingInfo['country'] ?? '',
                'ship_zip' => $shippingInfo['zip'] ?? $billingInfo['zip'] ?? '',
            ];
            
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['shipping_info' => json_encode($newShippingInfo)]);
            
            echo "[Order ID {$order->id}] Fixed shipping_info (Transaction: {$order->transaction_number})\n";
            $fixedCount++;
        } else {
            echo "[Order ID {$order->id}] OK - Already has correct format\n";
        }
    } catch (\Exception $e) {
        echo "[Order ID {$order->id}] ERROR: {$e->getMessage()}\n";
        $errorCount++;
    }
}

echo "\n";
echo "===========================================\n";
echo "PRODUCTION: Fix completed!\n";
echo "===========================================\n";
echo "Orders fixed: {$fixedCount}\n";
echo "Errors encountered: {$errorCount}\n";
echo "===========================================\n\n";
echo "IMPORTANT: Delete this file for security!\n";
echo "Run: rm fix_production_api_orders.php\n";
echo "===========================================\n";

