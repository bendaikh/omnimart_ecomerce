<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Website name (e.g., senytv.com)
            $table->string('domain')->unique(); // Website domain
            $table->string('email'); // Contact email
            $table->text('description')->nullable(); // Website description
            $table->string('api_key', 64)->unique(); // API key for authentication
            $table->string('api_secret', 128); // API secret (hashed)
            $table->boolean('is_approved')->default(false); // Approval status
            $table->boolean('is_active')->default(true); // Active/suspended
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->json('allowed_ips')->nullable(); // Optional IP whitelist
            $table->decimal('commission_rate', 5, 2)->default(0); // Commission percentage if applicable
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_clients');
    }
}

