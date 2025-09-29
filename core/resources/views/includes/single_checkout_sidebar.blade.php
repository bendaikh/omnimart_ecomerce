<aside class="sidebar">
    <div class="padding-top-2x hidden-lg-up"></div>
    <!-- Items in Cart Widget-->

    <section class="card widget widget-featured-posts widget-order-summary p-4">
        <h3 class="widget-title">{{ __('Order Summary') }}</h3>
        @php
            $free_shipping = DB::table('shipping_services')->whereStatus(1)->whereIsCondition(1)->first();
        @endphp

        @if ($free_shipping)
            @if ($free_shipping->minimum_price >= $cart_total)
                <p class="free-shippin-aa"><em>{{ __('Free Shipping After Order') }}
                        {{ PriceHelper::setCurrencyPrice($free_shipping->minimum_price) }}</em></p>
            @endif
        @endif

        <table class="table">
            <tr>
                <td>{{ __('Cart subtotal') }}:</td>
                <td class="text-gray-dark">{{ PriceHelper::setCurrencyPrice($cart_total) }}</td>
            </tr>

            @if ($tax != 0)
                <tr>
                    <td>{{ __('Estimated tax') }}:</td>
                    <td class="text-gray-dark">{{ PriceHelper::setCurrencyPrice($tax) }}</td>
                </tr>
            @endif

            @if (DB::table('states')->count() > 0)
                <tr class="{{ Auth::check() && Auth::user()->state_id ? '' : 'd-none' }} set__state_price_tr">
                    <td>{{ __('State tax') }}:</td>
                    <td class="text-gray-dark set__state_price">
                        {{ PriceHelper::setCurrencyPrice(Auth::check() && Auth::user()->state_id ? ($cart_total * Auth::user()->state->price) / 100 : 0) }}
                    </td>
                </tr>
            @endif

            @if ($discount)
                <tr>
                    <td>{{ __('Coupon discount') }}:</td>
                    <td class="text-danger">-
                        {{ PriceHelper::setCurrencyPrice($discount ? $discount['discount'] : 0) }}</td>
                </tr>
            @endif

            @if ($shipping)
                <tr class="d-none set__shipping_price_tr">
                    <td>{{ __('Shipping') }}:</td>
                    <td class="text-gray-dark set__shipping_price">
                        {{ PriceHelper::setCurrencyPrice($shipping ? $shipping->price : 0) }}</td>
                </tr>
            @endif
            <tr>
                <td class="text-lg text-primary">{{ __('Order total') }}</td>
                <td class="text-lg text-primary grand_total_set">{{ PriceHelper::setCurrencyPrice($grand_total) }}
                </td>
            </tr>
        </table>
    </section>

    @if (PriceHelper::CheckDigital() == true)
    <section class="card widget widget-featured-posts widget-order-summary p-4">
        <h3 class="widget-title">{{ __('Shipping Options') }}</h3>
        <div class="row">
            <div class="col-sm-12 mb-3">
                @if (PriceHelper::CheckDigital() == true)
                    @php
                        $free_shipping = DB::table('shipping_services')->whereStatus(1)->whereIsCondition(1)->first();
                    @endphp

                    <select name="shipping_id" class="form-control" id="shipping_id_select" required>
                        <option value="" selected disabled>{{ __('Select Shipping Method') }}*</option>
                        @foreach (DB::table('shipping_services')->whereStatus(1)->get() as $shipping)
                            @if ($shipping->id == 1 && isset($free_shipping) && $free_shipping->minimum_price <= $cart_total)
                                <option value="{{ $shipping->id }}" data-href="{{ route('front.shipping.setup') }}">
                                    {{ $shipping->title }}
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
                    @error('shipping_id')
                        <p class="text-danger shipping_message">{{ $message }}</p>
                    @enderror

                @endif
            </div>
            <div class="col-sm-12 mb-3">
                @if (PriceHelper::CheckDigital() == true)
                    @if (DB::table('states')->whereStatus(1)->count() > 0)
                        <select name="state_id" class="form-control" id="state_id_select" required>
                            <option value="" selected disabled>{{ __('Select Shipping State') }}*</option>
                            @foreach (DB::table('states')->whereStatus(1)->get() as $state)
                                <option value="{{ $state->id }}" data-href="{{ route('front.state.setup') }}"
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
                        @error('state_id')
                            <p class="text-danger state_message">{{ $message }}</p>
                        @enderror
                    @endif
                @endif
            </div>
        </div>

    </section>
    @endif



    <!-- Order Summary Widget-->
    <section class="card widget  widget-order-summary p-4 mb-0">
        <h3 class="widget-title">{{ __('Pay now') }}</h3>
        <div class="row">
            <div class="col-sm-12">
                <!-- Payment method selection hidden - DodoPayments is the default -->
                <input type="hidden" class="payment_gateway" value="dodopayments">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-credit-card"></i>
                    {{ __('Payment will be processed securely') }}
                </div>

                @if ($setting->is_privacy_trams == 1)
                    <div class="form-group mt-4">
                        <div class="custom-control d-flex custom-checkbox">
                            <input class="custom-control-input me-2" type="checkbox" id="trams__condition_single"
                                value="">
                            <label class="custom-control-label flex-1" for="trams__condition">This site is protected by
                                reCAPTCHA
                                and the <a href="{{ $setting->policy_link }}" target="_blank">Privacy Policy</a> and <a
                                    href="{{ $setting->terms_link }}" target="_blank">Terms of Service</a>
                                apply.</label>
                        </div>
                    </div>
                @endif

                <button id="single_checkout_payment" disabled="true"
                    class="btn btn-primary mt-4 single_checkout_payment" type="submit"><span>@lang('Pay now')</span></button>
            </div>

        </div>
    </section>

</aside>

@section('script')
    <script>
        // Direct DodoPayments checkout on #single_checkout_payment click
        $(document).on("click", "#single_checkout_payment", function() {
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
            
            // Add all select fields from the #checkoutBilling form
            $("#checkoutBilling select").each(function() {
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
                        resetButton();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Payment submission error:', error);
                    alert('Payment initialization failed. Please try again.');
                    resetButton();
                }
            });
            
            function resetButton() {
                $('#single_checkout_payment').prop('disabled', false);
                $('#single_checkout_payment').html('<span>{{ __("Pay now") }}</span>');
            }
        });

        // Handle the "Terms and Conditions" checkbox click
        $(document).on("click", "#trams__condition_single", function() {
            if ($("#trams__condition_single").is(':checked')) {
                console.log("check");
                // Enable the dropdown by assigning the ID and removing the disabled attribute
                $('.single_checkout_payment').attr('id', "single_checkout_payment");
                $('.single_checkout_payment').attr('disabled', false);
            } else {
                // Remove the ID and disable the dropdown when unchecked
                $('.single_checkout_payment').removeAttr('id');
                $('.single_checkout_payment').attr('disabled', true);
            }
        });

        // Auto-enable Pay Now button since DodoPayments is the default payment method
        $(document).ready(function() {
            // Set DodoPayments as the selected payment method
            $('.payment_gateway').val('dodopayments');
            
            // If terms and conditions are not required, enable the button immediately
            @if(!$setting->is_privacy_trams)
                $('.single_checkout_payment').attr('id', "single_checkout_payment");
                $('.single_checkout_payment').attr('disabled', false);
            @endif
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
            resetButton();
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
                resetButton();
            });
            
            overlay.appendChild(iframe);
            overlay.appendChild(closeBtn);
            document.body.appendChild(overlay);
        }

        function resetButton() {
            $('#single_checkout_payment').prop('disabled', false);
            $('#single_checkout_payment').html('<span>{{ __("Pay now") }}</span>');
        }
    </script>
@endsection
