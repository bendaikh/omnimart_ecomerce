@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0"><b>{{ __('Add New API Client') }}</b></h3>
                <a href="{{ route('back.api-clients.index') }}" class="btn btn-primary btn-sm">
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
                    
                    <form action="{{ route('back.api-clients.store') }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <label for="name">{{ __('Website Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="{{ old('name') }}" placeholder="e.g., SenyTV" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="domain">{{ __('Domain') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="domain" name="domain" 
                                   value="{{ old('domain') }}" placeholder="e.g., senytv.com" required>
                            <small class="form-text text-muted">{{ __('Enter the domain without http:// or https://') }}</small>
                            @error('domain')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="email">{{ __('Contact Email') }} <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="{{ old('email') }}" placeholder="contact@example.com" required>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">{{ __('Description') }}</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            <small class="form-text text-muted">{{ __('Brief description of the website/purpose') }}</small>
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="commission_rate">{{ __('Commission Rate (%)') }}</label>
                            <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                                   value="{{ old('commission_rate', 0) }}" step="0.01" min="0" max="100">
                            <small class="form-text text-muted">{{ __('Optional: Set a commission rate if applicable') }}</small>
                            @error('commission_rate')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="allowed_ips">{{ __('Allowed IP Addresses') }}</label>
                            <input type="text" class="form-control" id="allowed_ips" name="allowed_ips" 
                                   value="{{ old('allowed_ips') }}" placeholder="192.168.1.1, 10.0.0.1">
                            <small class="form-text text-muted">{{ __('Optional: Comma-separated list of IPs. Leave empty to allow all IPs.') }}</small>
                            @error('allowed_ips')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            {{ __('API credentials will be generated automatically after creating the client.') }}
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> {{ __('Create Client') }}
                        </button>
                        <a href="{{ route('back.api-clients.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
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
                    <h6 class="font-weight-bold">{{ __('About API Clients') }}</h6>
                    <p class="small">{{ __('API clients are external websites that can send payment requests to this platform.') }}</p>
                    
                    <h6 class="font-weight-bold mt-3">{{ __('Approval Required') }}</h6>
                    <p class="small">{{ __('New clients need to be approved before they can make API requests.') }}</p>
                    
                    <h6 class="font-weight-bold mt-3">{{ __('Security') }}</h6>
                    <p class="small">{{ __('Each client will receive a unique API key and secret for authentication.') }}</p>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- End of Main Content -->

@endsection

