@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('Transactions for') }} {{ $client->name }}</b></h3>
                <a href="{{ route('back.api-clients.show', $client->id) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Client') }}
                </a>
            </div>
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
							<th>{{ __('External Order') }}</th>
							<th>{{ __('Amount') }}</th>
							<th>{{ __('Status') }}</th>
							<th>{{ __('Payment Status') }}</th>
							<th>{{ __('Date') }}</th>
							<th>{{ __('Actions') }}</th>
						</tr>
					</thead>

					<tbody>
              			@forelse($transactions as $transaction)
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
                            <td>
                                @if($transaction->payment_status)
                                    @if($transaction->payment_status == 'Paid')
                                        <span class="badge badge-success">{{ $transaction->payment_status }}</span>
                                    @elseif($transaction->payment_status == 'Unpaid')
                                        <span class="badge badge-warning">{{ $transaction->payment_status }}</span>
                                    @elseif($transaction->payment_status == 'Refunded')
                                        <span class="badge badge-info">{{ $transaction->payment_status }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ $transaction->payment_status }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <a href="{{ route('back.api-transactions.show', $transaction->id) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> {{ __('View') }}
                                </a>
                                @if($transaction->order_id)
                                <a href="{{ route('back.order.edit', $transaction->order_id) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-receipt"></i> {{ __('Order') }}
                                </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">{{ __('No transactions found') }}</td>
                        </tr>
                        @endforelse
					</tbody>

				</table>
			</div>
            
            <!-- Pagination -->
            <div class="mt-3">
                {{ $transactions->links() }}
            </div>
		</div>
	</div>

</div>
<!-- End of Main Content -->

@endsection

