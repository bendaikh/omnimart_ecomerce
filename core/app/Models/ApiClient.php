<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'domain',
        'email',
        'description',
        'api_key',
        'api_secret',
        'is_approved',
        'is_active',
        'approved_at',
        'last_used_at',
        'last_ip',
        'allowed_ips',
        'commission_rate'
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'allowed_ips' => 'array',
        'approved_at' => 'datetime',
        'last_used_at' => 'datetime',
        'commission_rate' => 'decimal:2'
    ];

    protected $hidden = [
        'api_secret'
    ];

    /**
     * Generate a unique API key
     */
    public static function generateApiKey()
    {
        do {
            $key = 'sk_' . Str::random(48);
        } while (self::where('api_key', $key)->exists());

        return $key;
    }

    /**
     * Generate a unique API secret
     */
    public static function generateApiSecret()
    {
        return Str::random(64);
    }

    /**
     * Hash the API secret
     */
    public function setApiSecretAttribute($value)
    {
        $this->attributes['api_secret'] = bcrypt($value);
    }

    /**
     * Check if API secret is valid
     */
    public function validateSecret($secret)
    {
        return password_verify($secret, $this->api_secret);
    }

    /**
     * Check if IP is allowed
     */
    public function isIpAllowed($ip)
    {
        if (empty($this->allowed_ips)) {
            return true; // No IP restriction
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed($ip)
    {
        $this->last_used_at = now();
        $this->last_ip = $ip;
        $this->save();
    }

    /**
     * Get transactions
     */
    public function transactions()
    {
        return $this->hasMany(ApiTransaction::class);
    }

    /**
     * Get orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope: Only approved clients
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope: Only active clients
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

