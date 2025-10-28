<?php
/**
 * Fix double-encoded JSON in api_transactions table for PRODUCTION
 * 
 * INSTRUCTIONS FOR PRODUCTION:
 * 1. Upload this file to your production root directory: /home/u770530650/domains/senypro.com/public_html/
 * 2. SSH into your server or use cPanel Terminal
 * 3. Run: cd /home/u770530650/domains/senypro.com/public_html && php fix_production_api_transactions.php
 * 4. Delete this file after running
 */

require __DIR__ . '/core/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "===========================================\n";
echo "PRODUCTION: Fixing double-encoded JSON\n";
echo "===========================================\n\n";

$transactions = DB::table('api_transactions')->get();
$fixedCount = 0;
$errorCount = 0;

echo "Found " . count($transactions) . " transactions to check...\n\n";

foreach ($transactions as $transaction) {
    $updates = [];
    $hasIssues = false;
    
    // Fix request_data
    if (!empty($transaction->request_data)) {
        try {
            $decoded = json_decode($transaction->request_data, true);
            if (is_string($decoded)) {
                // It's double-encoded
                $properData = json_decode($decoded, true);
                if (is_array($properData)) {
                    $updates['request_data'] = json_encode($properData);
                    echo "[ID {$transaction->id}] Fixed request_data\n";
                    $hasIssues = true;
                }
            }
        } catch (\Exception $e) {
            echo "[ID {$transaction->id}] ERROR fixing request_data: {$e->getMessage()}\n";
            $errorCount++;
        }
    }
    
    // Fix response_data
    if (!empty($transaction->response_data)) {
        try {
            $decoded = json_decode($transaction->response_data, true);
            if (is_string($decoded)) {
                // It's double-encoded
                $properData = json_decode($decoded, true);
                if (is_array($properData)) {
                    $updates['response_data'] = json_encode($properData);
                    echo "[ID {$transaction->id}] Fixed response_data\n";
                    $hasIssues = true;
                }
            }
        } catch (\Exception $e) {
            echo "[ID {$transaction->id}] ERROR fixing response_data: {$e->getMessage()}\n";
            $errorCount++;
        }
    }
    
    // Fix customer_info
    if (!empty($transaction->customer_info)) {
        try {
            $decoded = json_decode($transaction->customer_info, true);
            if (is_string($decoded)) {
                // It's double-encoded
                $properData = json_decode($decoded, true);
                if (is_array($properData)) {
                    $updates['customer_info'] = json_encode($properData);
                    echo "[ID {$transaction->id}] Fixed customer_info\n";
                    $hasIssues = true;
                }
            }
        } catch (\Exception $e) {
            echo "[ID {$transaction->id}] ERROR fixing customer_info: {$e->getMessage()}\n";
            $errorCount++;
        }
    }
    
    // Fix product_info
    if (!empty($transaction->product_info)) {
        try {
            $decoded = json_decode($transaction->product_info, true);
            if (is_string($decoded)) {
                // It's double-encoded
                $properData = json_decode($decoded, true);
                if (is_array($properData)) {
                    $updates['product_info'] = json_encode($properData);
                    echo "[ID {$transaction->id}] Fixed product_info\n";
                    $hasIssues = true;
                }
            }
        } catch (\Exception $e) {
            echo "[ID {$transaction->id}] ERROR fixing product_info: {$e->getMessage()}\n";
            $errorCount++;
        }
    }
    
    // Apply updates if any
    if (!empty($updates)) {
        try {
            DB::table('api_transactions')
                ->where('id', $transaction->id)
                ->update($updates);
            $fixedCount++;
        } catch (\Exception $e) {
            echo "[ID {$transaction->id}] ERROR updating database: {$e->getMessage()}\n";
            $errorCount++;
        }
    } else if (!$hasIssues) {
        echo "[ID {$transaction->id}] OK - No issues found\n";
    }
}

echo "\n";
echo "===========================================\n";
echo "PRODUCTION: Fix completed!\n";
echo "===========================================\n";
echo "Transactions fixed: {$fixedCount}\n";
echo "Errors encountered: {$errorCount}\n";
echo "===========================================\n\n";
echo "IMPORTANT: Delete this file for security!\n";
echo "Run: rm fix_production_api_transactions.php\n";
echo "===========================================\n";

