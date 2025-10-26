@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('API Client Details') }}</b></h3>
                <div>
                    <a href="{{ route('back.api-clients.edit', $client->id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> {{ __('Edit') }}
                    </a>
                    <a href="{{ route('back.api-clients.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    @include('alerts.alerts')

    <!-- API Credentials Display (only shown once after creation) -->
    @if($apiCredentials)
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-key"></i> {{ __('API Credentials Generated') }}</h5>
        <p><strong>{{ __('Important:') }}</strong> {{ __('Save these credentials now. They will not be shown again!') }}</p>
        
        <div class="bg-white p-3 rounded border mt-2">
            <div class="mb-2">
                <strong>{{ __('API Key:') }}</strong>
                <div class="input-group mt-1">
                    <input type="text" class="form-control" id="api-key" value="{{ $apiCredentials['api_key'] }}" readonly>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('api-key')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="mb-0">
                <strong>{{ __('API Secret:') }}</strong>
                <div class="input-group mt-1">
                    <input type="text" class="form-control" id="api-secret" value="{{ $apiCredentials['api_secret'] }}" readonly>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('api-secret')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

	<!-- Client Details -->
	<div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Client Information') }}</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">{{ __('Name') }}</th>
                            <td>{{ $client->name }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Domain') }}</th>
                            <td>{{ $client->domain }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Email') }}</th>
                            <td>{{ $client->email }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Description') }}</th>
                            <td>{{ $client->description ?: __('N/A') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('API Key') }}</th>
                            <td>
                                <code>{{ $client->api_key }}</code>
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Commission Rate') }}</th>
                            <td>{{ $client->commission_rate }}%</td>
                        </tr>
                        <tr>
                            <th>{{ __('Allowed IPs') }}</th>
                            <td>
                                @if($client->allowed_ips)
                                    @foreach($client->allowed_ips as $ip)
                                        <span class="badge badge-secondary">{{ $ip }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">{{ __('All IPs allowed') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Status') }}</th>
                            <td>
                                @if($client->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-danger">{{ __('Suspended') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Approval Status') }}</th>
                            <td>
                                @if($client->is_approved)
                                    <span class="badge badge-success">{{ __('Approved') }}</span>
                                    <small class="text-muted">({{ $client->approved_at->format('Y-m-d H:i') }})</small>
                                @else
                                    <span class="badge badge-warning">{{ __('Pending Approval') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Last Used') }}</th>
                            <td>
                                @if($client->last_used_at)
                                    {{ $client->last_used_at->format('Y-m-d H:i:s') }}
                                    <small class="text-muted">({{ $client->last_used_at->diffForHumans() }})</small>
                                @else
                                    <span class="text-muted">{{ __('Never') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Last IP') }}</th>
                            <td>{{ $client->last_ip ?: __('N/A') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Created At') }}</th>
                            <td>{{ $client->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Recent Transactions') }}</h6>
                    <a href="{{ route('back.api-clients.transactions', $client->id) }}" class="btn btn-sm btn-primary">
                        {{ __('View All') }}
                    </a>
                </div>
                <div class="card-body">
                    @if($recentTransactions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('Request ID') }}</th>
                                    <th>{{ __('External Order') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransactions as $transaction)
                                <tr>
                                    <td><code>{{ $transaction->request_id }}</code></td>
                                    <td>{{ $transaction->external_order_id }}</td>
                                    <td>{{ $transaction->currency }} {{ number_format($transaction->amount, 2) }}</td>
                                    <td>
                                        @if($transaction->status == 'completed')
                                            <span class="badge badge-success">{{ ucfirst($transaction->status) }}</span>
                                        @elseif($transaction->status == 'failed')
                                            <span class="badge badge-danger">{{ ucfirst($transaction->status) }}</span>
                                        @elseif($transaction->status == 'processing')
                                            <span class="badge badge-info">{{ ucfirst($transaction->status) }}</span>
                                        @else
                                            <span class="badge badge-warning">{{ ucfirst($transaction->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center text-muted">{{ __('No transactions yet') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Statistics') }}</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>{{ __('Total Transactions:') }}</strong>
                        <span class="float-right badge badge-primary">{{ $client->transactions_count }}</span>
                    </div>
                    <div class="mb-3">
                        <strong>{{ __('Total Orders:') }}</strong>
                        <span class="float-right badge badge-primary">{{ $client->orders_count }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Actions') }}</h6>
                </div>
                <div class="card-body">
                    @if(!$client->is_approved)
                    <form action="{{ route('back.api-clients.approve', $client->id) }}" method="POST" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-check"></i> {{ __('Approve Client') }}
                        </button>
                    </form>
                    @else
                    <form action="{{ route('back.api-clients.revoke', $client->id) }}" method="POST" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-ban"></i> {{ __('Revoke Approval') }}
                        </button>
                    </form>
                    @endif

                    @if($client->is_active)
                    <form action="{{ route('back.api-clients.suspend', $client->id) }}" method="POST" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fas fa-pause"></i> {{ __('Suspend Client') }}
                        </button>
                    </form>
                    @else
                    <form action="{{ route('back.api-clients.activate', $client->id) }}" method="POST" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-play"></i> {{ __('Activate Client') }}
                        </button>
                    </form>
                    @endif

                    <hr>

                    <form action="{{ route('back.api-clients.regenerate', $client->id) }}" method="POST" class="mb-2" 
                          onsubmit="return confirm('{{ __('This will invalidate the current credentials. Are you sure?') }}')">
                        @csrf
                        <button type="submit" class="btn btn-info btn-block">
                            <i class="fas fa-sync"></i> {{ __('Regenerate Credentials') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- API Documentation -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('API Endpoint') }}</h6>
                </div>
                <div class="card-body">
                    <p class="small"><strong>{{ __('Base URL:') }}</strong></p>
                    <code>{{ url('/api/v1') }}</code>
                    
                    <p class="small mt-3"><strong>{{ __('Authentication:') }}</strong></p>
                    <p class="small">{{ __('Include these headers:') }}</p>
                    <code class="d-block">X-API-Key: [your-api-key]</code>
                    <code class="d-block">X-API-Secret: [your-api-secret]</code>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- End of Main Content -->

<script>
function copyToClipboard(elementId) {
    var copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    
    // Show feedback
    alert("{{ __('Copied to clipboard!') }}");
}
</script>

@endsection

