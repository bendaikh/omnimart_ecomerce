<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApiClientIdToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('api_client_id')->nullable()->after('user_id')->constrained('api_clients')->onDelete('set null');
            $table->string('external_order_id')->nullable()->after('api_client_id'); // Original order ID from external website
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['api_client_id']);
            $table->dropColumn(['api_client_id', 'external_order_id']);
        });
    }
}

