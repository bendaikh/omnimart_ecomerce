@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('Edit API Client') }}</b></h3>
                <a href="{{ route('back.api-clients.show', $client->id) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>

	<!-- Form -->
	<div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-body">
                    @include('alerts.alerts')
                    
                    <form action="{{ route('back.api-clients.update', $client->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="name">{{ __('Website Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="{{ old('name', $client->name) }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="domain">{{ __('Domain') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="domain" name="domain" 
                                   value="{{ old('domain', $client->domain) }}" required>
                            <small class="form-text text-muted">{{ __('Enter the domain without http:// or https://') }}</small>
                            @error('domain')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="email">{{ __('Contact Email') }} <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="{{ old('email', $client->email) }}" required>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">{{ __('Description') }}</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $client->description) }}</textarea>
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="commission_rate">{{ __('Commission Rate (%)') }}</label>
                            <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                                   value="{{ old('commission_rate', $client->commission_rate) }}" step="0.01" min="0" max="100">
                            @error('commission_rate')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="allowed_ips">{{ __('Allowed IP Addresses') }}</label>
                            <input type="text" class="form-control" id="allowed_ips" name="allowed_ips" 
                                   value="{{ old('allowed_ips', is_array($client->allowed_ips) ? implode(', ', $client->allowed_ips) : '') }}" 
                                   placeholder="192.168.1.1, 10.0.0.1">
                            <small class="form-text text-muted">{{ __('Comma-separated list of IPs. Leave empty to allow all IPs.') }}</small>
                            @error('allowed_ips')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> {{ __('Update Client') }}
                        </button>
                        <a href="{{ route('back.api-clients.show', $client->id) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Information') }}</h6>
                </div>
                <div class="card-body">
                    <h6 class="font-weight-bold">{{ __('Current Status') }}</h6>
                    <p class="small">
                        @if($client->is_approved)
                            <span class="badge badge-success">{{ __('Approved') }}</span>
                        @else
                            <span class="badge badge-warning">{{ __('Pending') }}</span>
                        @endif
                        
                        @if($client->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge badge-danger">{{ __('Suspended') }}</span>
                        @endif
                    </p>
                    
                    <h6 class="font-weight-bold mt-3">{{ __('Statistics') }}</h6>
                    <p class="small">
                        {{ __('Transactions:') }} {{ $client->transactions()->count() }}<br>
                        {{ __('Last Used:') }} {{ $client->last_used_at ? $client->last_used_at->diffForHumans() : __('Never') }}
                    </p>
                    
                    <h6 class="font-weight-bold mt-3">{{ __('Note') }}</h6>
                    <p class="small">{{ __('API credentials cannot be changed here. Use "Regenerate Credentials" button on the details page.') }}</p>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- End of Main Content -->

@endsection

