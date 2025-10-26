<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| External Website Payment API Routes
|--------------------------------------------------------------------------
|
| These routes allow external websites to create orders and process 
| payments via the senypro.com platform using Dodopayments.
|
*/

Route::prefix('v1')->middleware(['api.client'])->group(function () {
    // Create a new order/payment request
    Route::post('/orders', 'Api\PaymentController@createOrder');
    
    // Get order status by request ID
    Route::get('/orders/{requestId}', 'Api\PaymentController@getOrderStatus');
    
    // List all transactions for the authenticated client
    Route::get('/transactions', 'Api\PaymentController@listTransactions');
});


