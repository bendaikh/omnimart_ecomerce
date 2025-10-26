@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('All API Transactions') }}</b></h3>
                <a href="{{ route('back.api-clients.index') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-users"></i> {{ __('View Clients') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('back.api-transactions.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="client_id">{{ __('Client') }}</label>
                            <select name="client_id" id="client_id" class="form-control">
                                <option value="">{{ __('All Clients') }}</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status">{{ __('Status') }}</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">{{ __('All') }}</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                                <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>{{ __('Processing') }}</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="payment_status">{{ __('Payment') }}</label>
                            <select name="payment_status" id="payment_status" class="form-control">
                                <option value="">{{ __('All') }}</option>
                                <option value="Paid" {{ request('payment_status') == 'Paid' ? 'selected' : '' }}>{{ __('Paid') }}</option>
                                <option value="Unpaid" {{ request('payment_status') == 'Unpaid' ? 'selected' : '' }}>{{ __('Unpaid') }}</option>
                                <option value="Pending" {{ request('payment_status') == 'Pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                                <option value="Refunded" {{ request('payment_status') == 'Refunded' ? 'selected' : '' }}>{{ __('Refunded') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="search">{{ __('Search') }}</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="{{ __('Request ID or External Order ID') }}" value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-filter"></i> {{ __('Filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

	<!-- Transactions Table -->
	<div class="card shadow mb-4">
		<div class="card-body">
			@include('alerts.alerts')
			<div class="gd-responsive-table">
				<table class="table table-bordered table-striped" width="100%" cellspacing="0">

					<thead>
						<tr>
							<th>{{ __('Request ID') }}</th>
							<th>{{ __('Client') }}</th>
							<th>{{ __('External Order') }}</th>
							<th>{{ __('Amount') }}</th>
							<th>{{ __('Status') }}</th>
							<th>{{ __('Payment') }}</th>
							<th>{{ __('Date') }}</th>
							<th>{{ __('Actions') }}</th>
						</tr>
					</thead>

					<tbody>
              			@forelse($transactions as $transaction)
                        <tr>
                            <td><code>{{ Str::limit($transaction->request_id, 20) }}</code></td>
                            <td>
                                <a href="{{ route('back.api-clients.show', $transaction->apiClient->id) }}">
                                    {{ $transaction->apiClient->name }}
                                </a>
                            </td>
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
                            <td>
                                @if($transaction->payment_status)
                                    @if($transaction->payment_status == 'Paid')
                                        <span class="badge badge-success">{{ $transaction->payment_status }}</span>
                                    @elseif($transaction->payment_status == 'Unpaid')
                                        <span class="badge badge-warning">{{ $transaction->payment_status }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ $transaction->payment_status }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <a href="{{ route('back.api-transactions.show', $transaction->id) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($transaction->order_id)
                                <a href="{{ route('back.order.edit', $transaction->order_id) }}" class="btn btn-sm btn-primary" title="{{ __('View Order') }}">
                                    <i class="fas fa-receipt"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">{{ __('No transactions found') }}</td>
                        </tr>
                        @endforelse
					</tbody>

				</table>
			</div>
            
            <!-- Pagination -->
            <div class="mt-3">
                {{ $transactions->appends(request()->except('page'))->links() }}
            </div>
		</div>
	</div>

</div>
<!-- End of Main Content -->

@endsection

