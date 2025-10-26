<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->string('external_order_id')->nullable(); // Order ID from external website
            $table->string('request_id')->unique(); // Unique ID for each API request
            $table->string('status'); // pending, processing, completed, failed
            $table->text('request_data'); // JSON encoded request data
            $table->text('response_data')->nullable(); // JSON encoded response data
            $table->string('payment_status')->nullable(); // Paid, Unpaid, Refunded
            $table->string('payment_id')->nullable(); // Dodopayments payment ID
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            $table->text('error_message')->nullable();
            $table->ipAddress('request_ip');
            $table->text('customer_info')->nullable(); // JSON encoded customer details
            $table->text('product_info')->nullable(); // JSON encoded product details
            $table->timestamps();
            
            $table->index(['api_client_id', 'status']);
            $table->index(['external_order_id']);
            $table->index(['payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_transactions');
    }
}

