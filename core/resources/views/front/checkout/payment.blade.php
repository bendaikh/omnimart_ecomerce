@extends('master.front')
@section('title')
    {{ __('Payment') }}
@endsection
@section('content')
    <!-- Page Title-->
    <div class="page-title">
        <div class="container">
            <div class="column">
                <ul class="breadcrumbs">
                    <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a> </li>
                    <li class="separator"></li>
                    <li>{{ __('Review your order and pay') }}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Content-->
    <div class="container padding-bottom-3x mb-1 checkut-page">
        <div class="row">
            <!-- Payment Methode-->
            <div class="col-xl-9 col-lg-8">
                <div class="steps flex-sm-nowrap mb-5"> <a class="step" href="{{ route('front.checkout.billing') }}">
                        <h4 class="step-title"><i class="icon-check-circle"></i>1. {{ __('Invoice to') }}:</h4>
                    </a> <a class="step" href="{{ route('front.checkout.shipping') }}">
                        <h4 class="step-title"><i class="icon-check-circle"></i>2. {{ __('Ship to') }}:</h4>
                    </a> <a class="step active" href="{{ route('front.checkout.payment') }}">
                        <h4 class="step-title">3. {{ __('Review and pay') }}</h4>
                    </a>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h6 class="pb-2 widget-title2">{{ __('Review Your Order') }} :</h6>
                        
                        <div class="row">
                            <div class="col-sm-6 mb-4">
                                <h6 class="fz-16-bold">{{ __('Invoice address') }} :</h6>
                                @php

                                    $ship = Session::get('shipping_address');
                                    $bill = Session::get('billing_address');
                                @endphp
                                <ul class="list-unstyled">
                                    <li><span class="text-muted pay-label">{{ __('Name') }}:
                                        </span>{{ $ship['ship_first_name'] }} {{ $ship['ship_last_name'] }}</li>
                                    @if (PriceHelper::CheckDigital())
                                        <li><span class="text-muted pay-label">{{ __('Address') }}:
                                            </span>{{ $ship['ship_address1'] }} {{ @$ship['ship_address2'] }}</li>
                                    @endif
                                    <li><span class="text-muted pay-label">{{ __('Phone') }}: </span>{{ $ship['ship_phone'] }}
                                    </li>
                                </ul>
                            </div>
                            <div class="col-sm-6  mb-4">
                                <h6 class="fz-16-bold">{{ __('Shipping address') }} :</h6>
                                <ul class="list-unstyled">
                                    <li><span class="text-muted pay-label">{{ __('Name') }}:
                                        </span>{{ $bill['bill_first_name'] }} {{ $bill['bill_last_name'] }}</li>
                                    @if (PriceHelper::CheckDigital())
                                        <li><span class="text-muted pay-label">{{ __('Address') }}:
                                            </span>{{ $ship['ship_address1'] }} {{ @$ship['ship_address2'] }}</li>
                                    @endif
                                    <li><span class="text-muted pay-label">{{ __('Phone') }}: </span>{{ $bill['bill_phone'] }}
                                    </li>
                                </ul>

                              
                               
                            </div>
                        </div>
                        @if (PriceHelper::CheckDigital() == true)
                        <h6 class="pb-2 widget-title2">{{ __('Shipping Options') }} :</h6>
                        @endif
                        <div class="row">
                            <div class="col-sm-6  mb-4">
                                 @if (PriceHelper::CheckDigital() == true)
                                    
                            
                                    @php
                                        $free_shipping = DB::table('shipping_services')->whereStatus(1)->whereIsCondition(1)->first();
                                    @endphp
                            
                                    <select name="shipping_id" class="form-control" id="shipping_id_select" required>
                                        <option value="" selected disabled>{{ __('Select Shipping Method') }}</option>

                                        @foreach (DB::table('shipping_services')->whereStatus(1)->get() as $shipping)
                                            @if ($shipping->id == 1 && isset($free_shipping) &&  $free_shipping->minimum_price <= $cart_total)
                                                <option value="{{ $shipping->id }}"
                                                    data-href="{{ route('front.shipping.setup') }}">{{ $shipping->title }}
                                                </option>
                                            @else
                                                @if ($shipping->id != 1 && $free_shipping->minimum_price >= $cart_total)
                                                    <option value="{{ $shipping->id }}"
                                                        data-href="{{ route('front.shipping.setup') }}">{{ $shipping->title }}
                                                        ({{ PriceHelper::setCurrencyPrice($shipping->price) }})
                                                    </option>
                                                @endif
                                            @endif
                                        @endforeach
                                    </select>

                                    <small class="text-primary shipping_message">{{ __('Please select shipping method') }}</small>
                                    @error('shipping_id')
                                        <p class="text-danger shipping_message">{{ $message }}</p>
                                    @enderror

                                @endif
                            </div>
                            <div class="col-sm-6  mb-4">
                                @if (PriceHelper::CheckDigital() == true)
                                    
                                
                                @if (DB::table('states')->whereStatus(1)->count() > 0)
                                    <select name="state_id" class="form-control" id="state_id_select" required>
                                        <option value="" selected disabled>{{ __('Select Shipping State') }}</option>
                                        @foreach (DB::table('states')->whereStatus(1)->get() as $state)
                                            <option value="{{ $state->id }}"
                                                data-href="{{ route('front.state.setup') }}"
                                                {{ Auth::check() && Auth::user()->state_id == $state->id ? 'selected' : '' }}>
                                                {{ $state->name }}
                                                @if ($state->type == 'fixed')
                                                    ({{ PriceHelper::setCurrencyPrice($state->price) }})
                                                @else
                                                    ({{ $state->price }}%)
                                                @endif

                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-primary state_message">{{ __('Please select shipping state') }}</small>
                                    @error('state_id')
                                        <p class="text-danger state_message">{{ $message }}</p>
                                    @enderror
                                @endif
                            @endif
                            </div>
                        </div>
                        <!-- Payment method selection hidden - DodoPayments is the default -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-credit-card"></i>
                                    {{ __('Payment will be processed securely through DodoPayments') }}
                                </div>
                                
                                <!-- Direct Pay Now Button -->
                                <div class="text-center">
                                    <button id="checkout_pay_now" class="btn btn-primary btn-lg">
                                        <i class="fas fa-credit-card"></i> {{ __('Pay Now with DodoPayments') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('includes.checkout_modal')

            </div>
            <!-- Sidebar  -->
            <div class="col-xl-3 col-lg-4">
                @include('includes.checkout_sitebar',$cart)
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    // Direct DodoPayments checkout on main checkout page
    $(document).on("click", "#checkout_pay_now", function() {
        // Disable the button to prevent double-clicks
        $(this).prop('disabled', true);
        $(this).html('<i class="fas fa-spinner fa-spin"></i> {{ __("Processing...") }}');
        
        // Get all form data from the checkout form
        let formData = new FormData();
        
        // Add all input fields from the #checkoutBilling form
        $("#checkoutBilling input").each(function() {
            if ($(this).attr('name')) {
                formData.append($(this).attr('name'), $(this).val());
            }
        });
        
        // Add shipping and state data if available
        let shippingId = $('#shipping_id_select').val();
        let stateId = $('#state_id_select').val();
        
        if (shippingId) {
            formData.append('shipping_id', shippingId);
        }
        if (stateId) {
            formData.append('state_id', stateId);
        }
        
        // Add payment method
        formData.append('payment_method', 'DodoPayments');
        
        // Debug: Log form data
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Submit the form directly to checkout submit endpoint
        $.ajax({
            url: '{{ route("front.checkout.submit") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                console.log('DodoPayments response:', response);
                if (response.status && response.checkout_url) {
                    // Redirect directly to DodoPayments checkout
                    window.location.href = response.checkout_url;
                } else if (response.status && response.overlay_checkout) {
                    // Handle overlay checkout (fallback)
                    if (response.mock_payment) {
                        showMockPaymentInterface(response.payment_id);
                    } else {
                        initializeDodoPaymentsOverlay(response.payment_id, response.api_key);
                    }
                } else {
                    // Handle error
                    alert(response.message || 'Payment initialization failed');
                    resetCheckoutButton();
                }
            },
            error: function(xhr, status, error) {
                console.error('Payment submission error:', error);
                alert('Payment initialization failed. Please try again.');
                resetCheckoutButton();
            }
        });
        
        function resetCheckoutButton() {
            $('#checkout_pay_now').prop('disabled', false);
            $('#checkout_pay_now').html('<i class="fas fa-credit-card"></i> {{ __("Pay Now with DodoPayments") }}');
        }
    });

    // Helper functions for DodoPayments overlay checkout (fallback)
    function showMockPaymentInterface(paymentId) {
        // For localhost development, show a mock payment interface
        const mockInterface = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 30px; border-radius: 10px; max-width: 400px; width: 90%;">
                    <h3>Mock Payment Interface</h3>
                    <p>Payment ID: ${paymentId}</p>
                    <p>This is a mock payment for localhost development.</p>
                    <button onclick="simulatePaymentSuccess()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Simulate Payment Success</button>
                    <button onclick="closeMockInterface()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">Cancel</button>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', mockInterface);
    }

    function simulatePaymentSuccess() {
        // Simulate successful payment
        window.location.href = '{{ route("front.checkout.redirect") }}?status=success&payment_id=mock_payment_' + Date.now();
    }

    function closeMockInterface() {
        const mockInterface = document.querySelector('[style*="position: fixed"]');
        if (mockInterface) {
            mockInterface.remove();
        }
        resetCheckoutButton();
    }

    function initializeDodoPaymentsOverlay(paymentId, apiKey) {
        // Create overlay iframe for DodoPayments
        const overlay = document.createElement('div');
        overlay.id = 'dodopayments-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        const iframe = document.createElement('iframe');
        iframe.src = `https://checkout.dodopayments.com/payment/${paymentId}?api_key=${apiKey}`;
        iframe.style.cssText = `
            width: 90%;
            max-width: 500px;
            height: 80%;
            border: none;
            border-radius: 10px;
            background: white;
        `;
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 24px;
            cursor: pointer;
            z-index: 10000;
        `;
        
        closeBtn.addEventListener('click', function() {
            document.body.removeChild(overlay);
            resetCheckoutButton();
        });
        
        overlay.appendChild(iframe);
        overlay.appendChild(closeBtn);
        document.body.appendChild(overlay);
    }

    function resetCheckoutButton() {
        $('#checkout_pay_now').prop('disabled', false);
        $('#checkout_pay_now').html('<i class="fas fa-credit-card"></i> {{ __("Pay Now with DodoPayments") }}');
    }
</script>
@endsection
