@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('Transaction Details') }}</b></h3>
                <div>
                    @if($transaction->order_id)
                    <a href="{{ route('back.order.edit', $transaction->order_id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-receipt"></i> {{ __('View Order') }}
                    </a>
                    @endif
                    <a href="{{ route('back.api-transactions.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

	<!-- Transaction Details -->
	<div class="row">
        <div class="col-lg-8">
            <!-- Basic Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Transaction Information') }}</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">{{ __('Request ID') }}</th>
                            <td><code>{{ $transaction->request_id }}</code></td>
                        </tr>
                        <tr>
                            <th>{{ __('API Client') }}</th>
                            <td>
                                <a href="{{ route('back.api-clients.show', $transaction->apiClient->id) }}">
                                    {{ $transaction->apiClient->name }}
                                </a>
                                <br>
                                <small class="text-muted">{{ $transaction->apiClient->domain }}</small>
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('External Order ID') }}</th>
                            <td>{{ $transaction->external_order_id }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Internal Order ID') }}</th>
                            <td>
                                @if($transaction->order)
                                    <a href="{{ route('back.order.edit', $transaction->order->id) }}">
                                        {{ $transaction->order->transaction_number }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Amount') }}</th>
                            <td><strong>{{ $transaction->currency }} {{ number_format($transaction->amount, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <th>{{ __('Status') }}</th>
                            <td>
                                @if($transaction->status == 'completed')
                                    <span class="badge badge-success badge-lg">{{ ucfirst($transaction->status) }}</span>
                                @elseif($transaction->status == 'failed')
                                    <span class="badge badge-danger badge-lg">{{ ucfirst($transaction->status) }}</span>
                                @elseif($transaction->status == 'processing')
                                    <span class="badge badge-info badge-lg">{{ ucfirst($transaction->status) }}</span>
                                @else
                                    <span class="badge badge-warning badge-lg">{{ ucfirst($transaction->status) }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Payment Status') }}</th>
                            <td>
                                @if($transaction->payment_status)
                                    @if($transaction->payment_status == 'Paid')
                                        <span class="badge badge-success badge-lg">{{ $transaction->payment_status }}</span>
                                    @elseif($transaction->payment_status == 'Unpaid')
                                        <span class="badge badge-warning badge-lg">{{ $transaction->payment_status }}</span>
                                    @else
                                        <span class="badge badge-secondary badge-lg">{{ $transaction->payment_status }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Payment ID') }}</th>
                            <td>{{ $transaction->payment_id ?: __('N/A') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Request IP') }}</th>
                            <td>{{ $transaction->request_ip }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Created At') }}</th>
                            <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Updated At') }}</th>
                            <td>{{ $transaction->updated_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    </table>

                    @if($transaction->error_message)
                    <div class="alert alert-danger mt-3">
                        <strong>{{ __('Error Message:') }}</strong><br>
                        {{ $transaction->error_message }}
                    </div>
                    @endif
                </div>
            </div>

            <!-- Customer Info -->
            @if($transaction->customer_info)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Customer Information') }}</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        @foreach($transaction->customer_info as $key => $value)
                        <tr>
                            <th width="30%">{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                            <td>{{ $value }}</td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
            @endif

            <!-- Products -->
            @if($transaction->product_info)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Products') }}</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Quantity') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transaction->product_info as $product)
                            <tr>
                                <td>{{ $product['name'] }}</td>
                                <td>{{ $product['quantity'] }}</td>
                                <td>{{ $transaction->currency }} {{ number_format($product['price'], 2) }}</td>
                                <td>{{ $transaction->currency }} {{ number_format($product['price'] * $product['quantity'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <!-- Request Data -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Request Data') }}</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-2" style="max-height: 300px; overflow-y: auto; font-size: 11px;">{{ json_encode($transaction->request_data, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>

            <!-- Response Data -->
            @if($transaction->response_data)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Response Data') }}</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-2" style="max-height: 300px; overflow-y: auto; font-size: 11px;">{{ json_encode($transaction->response_data, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif
        </div>
    </div>

</div>
<!-- End of Main Content -->

@endsection

