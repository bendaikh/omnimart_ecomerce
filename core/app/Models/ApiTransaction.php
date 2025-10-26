<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiTransaction extends Model
{
    protected $fillable = [
        'api_client_id',
        'order_id',
        'external_order_id',
        'request_id',
        'status',
        'request_data',
        'response_data',
        'payment_status',
        'payment_id',
        'amount',
        'currency',
        'error_message',
        'request_ip',
        'customer_info',
        'product_info'
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'customer_info' => 'array',
        'product_info' => 'array',
        'amount' => 'decimal:2'
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Payment status constants
     */
    const PAYMENT_PAID = 'Paid';
    const PAYMENT_UNPAID = 'Unpaid';
    const PAYMENT_REFUNDED = 'Refunded';
    const PAYMENT_PENDING = 'Pending';

    /**
     * Get the API client
     */
    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }

    /**
     * Get the order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by payment status
     */
    public function scopePaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope: Filter by API client
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('api_client_id', $clientId);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing()
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted($orderId = null, $paymentId = null)
    {
        $data = ['status' => self::STATUS_COMPLETED];
        
        if ($orderId) {
            $data['order_id'] = $orderId;
        }
        
        if ($paymentId) {
            $data['payment_id'] = $paymentId;
        }

        $this->update($data);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($status)
    {
        $this->update(['payment_status' => $status]);
    }
}

