<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ApiClient;
use Illuminate\Http\Request;

class ApiClientAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get API key from header
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if (!$apiKey || !$apiSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Missing API credentials. Please provide X-API-Key and X-API-Secret headers.',
                'error_code' => 'MISSING_CREDENTIALS'
            ], 401);
        }

        // Find the API client
        $client = ApiClient::where('api_key', $apiKey)->first();

        if (!$client) {
            \Log::warning('API authentication failed: Invalid API key', [
                'api_key' => substr($apiKey, 0, 10) . '...',
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid API credentials.',
                'error_code' => 'INVALID_CREDENTIALS'
            ], 401);
        }

        // Verify API secret
        if (!$client->validateSecret($apiSecret)) {
            \Log::warning('API authentication failed: Invalid API secret', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid API credentials.',
                'error_code' => 'INVALID_CREDENTIALS'
            ], 401);
        }

        // Check if client is approved
        if (!$client->is_approved) {
            \Log::warning('API access denied: Client not approved', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your API access is pending approval. Please contact the administrator.',
                'error_code' => 'NOT_APPROVED'
            ], 403);
        }

        // Check if client is active
        if (!$client->is_active) {
            \Log::warning('API access denied: Client suspended', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your API access has been suspended. Please contact the administrator.',
                'error_code' => 'SUSPENDED'
            ], 403);
        }

        // Check IP whitelist if configured
        if (!$client->isIpAllowed($request->ip())) {
            \Log::warning('API access denied: IP not allowed', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'ip' => $request->ip(),
                'allowed_ips' => $client->allowed_ips
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Access denied from your IP address.',
                'error_code' => 'IP_NOT_ALLOWED'
            ], 403);
        }

        // Update last used timestamp
        $client->updateLastUsed($request->ip());

        // Attach client to request
        $request->merge(['api_client' => $client]);

        \Log::info('API authentication successful', [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'ip' => $request->ip()
        ]);

        return $next($request);
    }
}

