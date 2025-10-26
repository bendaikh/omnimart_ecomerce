<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApiClientController extends Controller
{
    /**
     * Display a listing of API clients
     */
    public function index()
    {
        $clients = ApiClient::withCount('transactions')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('back.api-clients.index', compact('clients'));
    }

    /**
     * Show the form for creating a new API client
     */
    public function create()
    {
        return view('back.api-clients.create');
    }

    /**
     * Store a newly created API client
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:api_clients,domain',
            'email' => 'required|email|max:255',
            'description' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'allowed_ips' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Generate API credentials
        $apiKey = ApiClient::generateApiKey();
        $apiSecret = ApiClient::generateApiSecret();

        // Parse allowed IPs
        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $ips = array_map('trim', explode(',', $request->allowed_ips));
            $ips = array_filter($ips);
            $allowedIps = !empty($ips) ? json_encode($ips) : null;
        }

        $client = new ApiClient();
        $client->name = $request->name;
        $client->domain = $request->domain;
        $client->email = $request->email;
        $client->description = $request->description;
        $client->api_key = $apiKey;
        $client->api_secret = $apiSecret; // Will be hashed by mutator
        $client->commission_rate = $request->commission_rate ?? 0;
        $client->allowed_ips = $allowedIps;
        $client->is_approved = false; // Needs approval
        $client->is_active = true;
        $client->save();

        // Store the plain secret temporarily to show to admin once
        session()->flash('api_credentials', [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret, // Plain text, only shown once
            'client_id' => $client->id
        ]);

        return redirect()->route('back.api-clients.show', $client->id)
            ->with('success', 'API Client created successfully. Please save the credentials below - they will not be shown again.');
    }

    /**
     * Display the specified API client
     */
    public function show($id)
    {
        $client = ApiClient::withCount('transactions', 'orders')->findOrFail($id);
        
        // Get recent transactions
        $recentTransactions = $client->transactions()
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get API credentials if just created
        $apiCredentials = session('api_credentials');
        if ($apiCredentials && $apiCredentials['client_id'] == $id) {
            session()->forget('api_credentials'); // Clear after displaying once
        } else {
            $apiCredentials = null;
        }

        return view('back.api-clients.show', compact('client', 'recentTransactions', 'apiCredentials'));
    }

    /**
     * Show the form for editing the specified API client
     */
    public function edit($id)
    {
        $client = ApiClient::findOrFail($id);
        return view('back.api-clients.edit', compact('client'));
    }

    /**
     * Update the specified API client
     */
    public function update(Request $request, $id)
    {
        $client = ApiClient::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:api_clients,domain,' . $id,
            'email' => 'required|email|max:255',
            'description' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'allowed_ips' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Parse allowed IPs
        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $ips = array_map('trim', explode(',', $request->allowed_ips));
            $ips = array_filter($ips);
            $allowedIps = !empty($ips) ? json_encode($ips) : null;
        }

        $client->name = $request->name;
        $client->domain = $request->domain;
        $client->email = $request->email;
        $client->description = $request->description;
        $client->commission_rate = $request->commission_rate ?? 0;
        $client->allowed_ips = $allowedIps;
        $client->save();

        return redirect()->route('back.api-clients.show', $client->id)
            ->with('success', 'API Client updated successfully.');
    }

    /**
     * Approve an API client
     */
    public function approve($id)
    {
        $client = ApiClient::findOrFail($id);
        
        if ($client->is_approved) {
            return redirect()->back()->with('warning', 'This client is already approved.');
        }

        $client->is_approved = true;
        $client->approved_at = now();
        $client->save();

        return redirect()->back()->with('success', 'API Client approved successfully.');
    }

    /**
     * Revoke approval for an API client
     */
    public function revoke($id)
    {
        $client = ApiClient::findOrFail($id);
        
        $client->is_approved = false;
        $client->approved_at = null;
        $client->save();

        return redirect()->back()->with('success', 'API Client approval revoked.');
    }

    /**
     * Activate an API client
     */
    public function activate($id)
    {
        $client = ApiClient::findOrFail($id);
        
        $client->is_active = true;
        $client->save();

        return redirect()->back()->with('success', 'API Client activated successfully.');
    }

    /**
     * Suspend an API client
     */
    public function suspend($id)
    {
        $client = ApiClient::findOrFail($id);
        
        $client->is_active = false;
        $client->save();

        return redirect()->back()->with('success', 'API Client suspended successfully.');
    }

    /**
     * Regenerate API credentials
     */
    public function regenerateCredentials($id)
    {
        $client = ApiClient::findOrFail($id);

        // Generate new credentials
        $apiKey = ApiClient::generateApiKey();
        $apiSecret = ApiClient::generateApiSecret();

        $client->api_key = $apiKey;
        $client->api_secret = $apiSecret; // Will be hashed by mutator
        $client->save();

        // Store credentials to show once
        session()->flash('api_credentials', [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'client_id' => $client->id
        ]);

        return redirect()->route('back.api-clients.show', $client->id)
            ->with('success', 'API credentials regenerated successfully. Please save them - they will not be shown again.');
    }

    /**
     * Remove the specified API client
     */
    public function destroy($id)
    {
        $client = ApiClient::findOrFail($id);
        
        // Soft delete
        $client->delete();

        return redirect()->route('back.api-clients.index')
            ->with('success', 'API Client deleted successfully.');
    }

    /**
     * Display transactions for a specific client
     */
    public function transactions($id)
    {
        $client = ApiClient::findOrFail($id);
        
        $transactions = $client->transactions()
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('back.api-clients.transactions', compact('client', 'transactions'));
    }

    /**
     * Display all API transactions (across all clients)
     */
    public function allTransactions(Request $request)
    {
        $query = ApiTransaction::with(['apiClient', 'order']);

        // Filter by client
        if ($request->filled('client_id')) {
            $query->where('api_client_id', $request->client_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Search by external order ID or request ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('external_order_id', 'like', "%{$search}%")
                  ->orWhere('request_id', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        $clients = ApiClient::orderBy('name')->get();

        return view('back.api-clients.all-transactions', compact('transactions', 'clients'));
    }

    /**
     * Show transaction details
     */
    public function transactionDetail($id)
    {
        $transaction = ApiTransaction::with(['apiClient', 'order'])->findOrFail($id);

        return view('back.api-clients.transaction-detail', compact('transaction'));
    }
}

