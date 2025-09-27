     <!-- Modal Cash on Transfer-->
     <div class="modal fade" id="cod" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h6 class="modal-title">{{ __('Transaction Cash On Delivery') }}</h6>
                     <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                             aria-hidden="true">&times;</span></button>
                 </div>
                 <form action="{{ route('front.checkout.submit') }}" method="POST">
                     @csrf
                     <input type="hidden" name="payment_method" value="Cash On Delivery" id="">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <div class="card-body">
                         <p>{{ PriceHelper::GatewayText('cod') }}</p>
                     </div>
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Cash On Delivery') }}</span></button>
                 </form>
             </div>
         </div>
     </div>
     </div>
     <!-- Modal MOLLIE -->
     <div class="modal fade" id="mollie" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h6 class="modal-title">{{ __('Transactions via Mollie') }}</h6>
                     <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                             aria-hidden="true">&times;</span></button>
                 </div>
                 <div class="modal-body">
                     <p>{{ PriceHelper::GatewayText('mollie') }}</p>
                 </div>
                 <div class="modal-footer">
                     <form action="{{ route('front.checkout.submit') }}" method="POST">
                         @csrf
                         <input type="hidden" name="payment_method" value="Mollie">
                         <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                         <input type="hidden" name="state_id"
                             value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                             class="state_id_setup">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With Mollie') }}</span></button>
                     </form>
                 </div>
             </div>
         </div>
     </div>
     <!-- Modal PayPal -->
     <div class="modal fade" id="paypal" tabindex="-1" aria-hidden="true">
         <form class="interactive-credit-card row" action="{{ route('front.checkout.submit') }}" method="POST">
             @csrf
             <div class="modal-dialog">

                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via PayPal') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('paypal') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="Paypal">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With PayPal') }}</span></button>
                     </div>
                 </div>

             </div>
         </form>
     </div>

     <!-- Modal Stripe -->
     <div class="modal fade" id="stripe" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h6 class="modal-title">{{ __('Transactions via Stripe') }}</h6>
                     <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                             aria-hidden="true">&times;</span></button>
                 </div>
                 <div class="modal-body">
                     <div class="card-body">

                         <form class="interactive-credit-card row" action="{{ route('front.checkout.submit') }}"
                             method="POST">
                             @csrf

                             <input type="hidden" name="payment_method" value="Stripe">
                             <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                             <input type="hidden" name="state_id"
                                 value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                                 class="state_id_setup">
                             <p class="p-3">{{ PriceHelper::GatewayText('stripe') }}</p>
                     </div>
                 </div>
                 <div class="modal-footer">
                     <button class="btn btn-primary btn-sm" type="button"
                         data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                     <button class="btn btn-primary btn-sm"
                         type="submit"><span>{{ __('Chekout With Stripe') }}</span></button>
                 </div>
                 </form>
             </div>
         </div>
     </div>

     <!-- Modal Authorize -->
     <div class="modal fade" id="authorize" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h6 class="modal-title">{{ __('Transactions via Authorize.Net') }}</h6>
                     <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                             aria-hidden="true">&times;</span></button>
                 </div>
                 <div class="modal-body">
                     <div class="card-body">
                         <div class="card-wrapper"></div>
                         <form class="interactive-credit-card row" action="{{ route('front.authorize.submit') }}"
                             method="POST">
                             @csrf
                             <div class="form-group col-sm-12">
                                 <input class="form-control" type="text" name="card"
                                     placeholder="{{ __('Card Number') }}" required>
                             </div>
                             <input type="hidden" name="payment_method" value="Authorize.Net">
                             <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                             <input type="hidden" name="state_id"
                                 value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                                 class="state_id_setup">
                             <div class="form-group col-sm-6">
                                 <input class="form-control" type="text" name="month"
                                     placeholder="{{ __('Expitation Month') }}" required>
                             </div>
                             <div class="form-group col-sm-6">
                                 <input class="form-control" type="text" name="year"
                                     placeholder="{{ __('Expitation Year') }}" required>
                             </div>
                             <div class="form-group col-sm-12">
                                 <input class="form-control" type="text" name="cvc"
                                     placeholder="{{ __('CVV') }}" required>
                             </div>

                             <p class="p-3">{{ PriceHelper::GatewayText('authorize') }}</p>
                     </div>
                 </div>
                 <div class="modal-footer">
                     <button class="btn btn-primary btn-sm" type="button"
                         data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                     <button class="btn btn-primary btn-sm"
                         type="submit"><span>{{ __('Chekout With Stripe') }}</span></button>
                 </div>
                 </form>
             </div>
         </div>
     </div>


     {{-- PAYPAL --}}
     <div class="modal fade" id="paypal" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <form class="interactive-credit-card row" action="{{ route('front.checkout.submit') }}" method="POST">
                 @csrf
                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via PayPal') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('paypal') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="Paypal">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With PayPal') }}</span></button>
                     </div>
                 </div>
             </form>
         </div>
     </div>


     {{-- REZORPAY --}}
     <div class="modal fade" id="razorpay" tabindex="-1" aria-hidden="true">
         <form class="interactive-credit-card row" action="{{ route('front.razorpay.submit') }}" method="POST">
             @csrf
             <div class="modal-dialog">

                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via Razorpay') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('razorpay') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="Rezorpay">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With Razorpay') }}</span></button>
                     </div>
                 </div>
             </div>
         </form>
     </div>

     {{-- Flutterwave --}}
     <div class="modal fade" id="flutterwave" tabindex="-1" aria-hidden="true">
         <form class="interactive-credit-card row" action="{{ route('front.flutterwave.submit') }}" method="POST">
             @csrf
             <div class="modal-dialog">

                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via Flutterwave') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('flutterwave') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="Flutterwave">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With Flutterwave') }}</span></button>
                     </div>
                 </div>
             </div>
         </form>
     </div>

     {{-- PAYTM --}}
     <div class="modal fade" id="paytm" tabindex="-1" aria-hidden="true">
         <form class="interactive-credit-card row" action="{{ route('front.paytm.submit') }}" method="POST">
             @csrf
             <div class="modal-dialog">
                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via Paytm') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('paytm') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="Paytm">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With Paytm') }}</span></button>
                     </div>
                 </div>
             </div>
         </form>
     </div>

     {{-- SSL COMMERZ --}}
     <div class="modal fade" id="sslcommerz" tabindex="-1" aria-hidden="true">
         <form class="interactive-credit-card row" action="{{ route('front.sslcommerz.submit') }}" method="POST">
             @csrf
             <div class="modal-dialog">
                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via SSLCommerz') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('sslcommerz') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="SSLCommerz">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With SSLCommerz') }}</span></button>
                     </div>
                 </div>

             </div>
         </form>
     </div>







     @php
         $paymentData = App\Models\PaymentSetting::where('unique_keyword', 'mercadopago')->first();
         $paydata = $paymentData->convertJsonData();
     @endphp

     @if ($paymentData->status == 1)
         {{-- MERCADOPAGO --}}
         <div class="modal fade" id="mercadopago" tabindex="-1" aria-hidden="true">
             <form class="interactive-credit-card row" id="mercadopagofrom"
                 action="{{ route('front.mercadopago.submit') }}" method="POST">
                 @csrf
                 <div class="modal-dialog">
                     <div class="modal-content">
                         <div class="modal-header">
                             <h6 class="modal-title">{{ __('Transactions via Mercadapago') }}</h6>
                             <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                     aria-hidden="true">&times;</span></button>
                         </div>
                         <div class="modal-body">
                             <div class="card-body">


                                 <div class="col-lg-12 form-group">
                                     <div id="cardNumber"></div>
                                 </div>
                                 <div class="col-lg-12 form-group">
                                     <div id="securityCode"> </div>
                                 </div>

                                 <div id="expirationDate"></div>

                                 <div class="col-lg-12 form-group">
                                     <input class="form-control" type="text" id="cardholderName"
                                         data-checkout="cardholderName" placeholder="{{ __('Card Holder Name') }}"
                                         required />
                                 </div>
                                 <div class="col-lg-12 form-group">
                                     <input class="form-control" type="text" id="docNumber"
                                         data-checkout="docNumber" placeholder="{{ __('Document Number') }}"
                                         required />
                                 </div>
                                 <div class="col-lg-12 form-group">
                                     <label for="docType" class="col-lg-3 pl-0"
                                         id="dc-label">{{ __('Document type') }}</label>
                                     <select id="docType" class="form-control" name="docType"
                                         data-checkout="docType"></select>
                                 </div>

                                 <p>{{ PriceHelper::GatewayText('mercadopago') }}</p>
                             </div>
                         </div>
                         <input type="hidden" name="payment_method" value="Mercadopago">
                         <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                         <input type="hidden" name="state_id"
                             value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                             class="state_id_setup">
                         <div class="modal-footer">
                             <button class="btn btn-primary btn-sm" type="button"
                                 data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                             <button class="btn btn-primary btn-sm"
                                 type="submit"><span>{{ __('Checkout With Mercadopago') }}</span></button>
                         </div>
                     </div>

                 </div>
                 <input type="hidden" id="installments" value="1" />
                 <input type="hidden" name="amount" id="transactionAmount" />
                 <input type="hidden" name="description" />
                 <input type="hidden" name="paymentMethodId" />
             </form>
             <script src="https://sdk.mercadopago.com/js/v2"></script>
             <script>
                 const mp = new MercadoPago("{{ $paydata['public_key'] }}");

                 const cardNumberElement = mp.fields.create('cardNumber', {
                     placeholder: "Card Number"
                 }).mount('cardNumber');

                 const expirationDateElement = mp.fields.create('expirationDate', {
                     placeholder: "MM/YY",
                 }).mount('expirationDate');

                 const securityCodeElement = mp.fields.create('securityCode', {
                     placeholder: "Security Code"
                 }).mount('securityCode');


                 (async function getIdentificationTypes() {
                     try {
                         const identificationTypes = await mp.getIdentificationTypes();

                         const identificationTypeElement = document.getElementById('docType');

                         createSelectOptions(identificationTypeElement, identificationTypes);

                     } catch (e) {
                         return console.error('Error getting identificationTypes: ', e);
                     }
                 })();

                 function createSelectOptions(elem, options, labelsAndKeys = {
                     label: "name",
                     value: "id"
                 }) {

                     const {
                         label,
                         value
                     } = labelsAndKeys;

                     //heem.options.length = 0;

                     const tempOptions = document.createDocumentFragment();

                     options.forEach(option => {
                         const optValue = option[value];
                         const optLabel = option[label];

                         const opt = document.createElement('option');
                         opt.value = optValue;
                         opt.textContent = optLabel;


                         tempOptions.appendChild(opt);
                     });

                     elem.appendChild(tempOptions);
                 }
                 cardNumberElement.on('binChange', getPaymentMethods);
                 async function getPaymentMethods(data) {
                     const {
                         bin
                     } = data
                     const {
                         results
                     } = await mp.getPaymentMethods({
                         bin
                     });
                     console.log(results);
                     return results[0];
                 }

                 async function getIssuers(paymentMethodId, bin) {
                     const issuears = await mp.getIssuers({
                         paymentMethodId,
                         bin
                     });
                     console.log(issuers)
                     return issuers;
                 };

                 async function getInstallments(paymentMethodId, bin) {
                     const installments = await mp.getInstallments({
                         amount: document.getElementById('transactionAmount').value,
                         bin,
                         paymentTypeId: 'credit_card'
                     });

                 };

                 async function createCardToken() {
                     const token = await mp.fields.createCardToken({
                         cardholderName,
                         identificationType,
                         identificationNumber,
                     });

                 }
                 doSubmit = false;
                 document.getElementById('mercadopagofrom').addEventListener('submit', getCardToken);

                 async function getCardToken(event) {
                     event.preventDefault();
                     if (!doSubmit) {
                         let $form = document.getElementById('mercadopagofrom');
                         const token = await mp.fields.createCardToken({
                             cardholderName: document.getElementById('cardholderName').value,
                             identificationType: document.getElementById('docType').value,
                             identificationNumber: document.getElementById('docNumber').value,
                         })
                         setCardTokenAndPay(token.id)
                     }
                 };

                 function setCardTokenAndPay(token) {
                     let form = document.getElementById('mercadopagofrom');
                     let card = document.createElement('input');
                     card.setAttribute('name', 'token');
                     card.setAttribute('type', 'hidden');
                     card.setAttribute('value', token);
                     form.appendChild(card);
                     doSubmit = true;
                     form.submit();
                 };
             </script>


         </div>
     @endif


     {{-- Paystack --}}
     <div class="modal fade" id="paystack" tabindex="-1" aria-hidden="true">

         <form class="interactive-credit-card row" action="{{ route('front.checkout.submit') }}" method="POST"
             id="paystack_form">
             @csrf
             <input type="hidden" name="ref_id" id="ref_id" value="">
             <div class="modal-dialog">
                 <div class="modal-content">
                     <div class="modal-header">
                         <h6 class="modal-title">{{ __('Transactions via Paystack') }}</h6>
                         <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                 aria-hidden="true">&times;</span></button>
                     </div>
                     <div class="modal-body">
                         <div class="card-body">
                             <p>{{ PriceHelper::GatewayText('paystack') }}</p>
                         </div>
                     </div>
                     <input type="hidden" name="payment_method" value="Paystack">
                     <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                     <input type="hidden" name="state_id"
                         value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                         class="state_id_setup">
                     <div class="modal-footer">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                         <button class="btn btn-primary btn-sm final-btn" id="final-btn"
                             type="submit"><span>{{ __('Checkout With Paystack') }}</span></button>
                     </div>
                 </div>
             </div>
         </form>


         @php
             $data = App\Models\PaymentSetting::whereUniqueKeyword('paystack')->first();
             $paydata = $data->convertJsonData();
             $billing = Session::get('billing_address');
         @endphp
         @section('script')
             <script src="https://js.paystack.co/v1/inline.js"></script>
             <script>
                 $(document).on('submit', '#paystack_form', function(e) {
                     e.preventDefault();
                     var total = $(".grand_total_set").text();
                     var currencyString = total;
                     var cleanedString = currencyString.replace(/[^0-9.-]+/g, '');
                     total = parseInt(cleanedString).toFixed(2);

                     let email = $('#checkout_email_billing').val();
                     alert(email)
                     var handler = PaystackPop.setup({
                         key: '{{ $paydata['key'] }}',
                         email: email,
                         amount: parseFloat(total).toFixed(2) * 100,
                         currency: '{{ PriceHelper::setCurrencyName() }}',
                         ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                         callback: function(response) {
                             $('#ref_id').val(response.reference);
                             $('#paystack_form').removeAttr('id');
                             $('.final-btn').click();
                         },
                         onClose: function() {
                             window.location.reload();
                         }
                     });
                     handler.openIframe();
                     return false;
                 });
             </script>
         @endsection
     </div>




     <!-- Modal bank -->
     <div class="modal fade" id="bank" tabindex="-1" aria-hidden="true">
         <div class="modal-dialog">
             <div class="modal-content">
                 <div class="modal-header">
                     <h6 class="modal-title">{{ __('Transactions via Bank Transfer') }}</h6>
                     <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                             aria-hidden="true">&times;</span></button>
                 </div>
                 <form action="{{ route('front.checkout.submit') }}" method="POST">
                     <div class="modal-body">
                         <div class="col-lg-12 form-group">
                             <label for="transaction">{{ __('Transaction Number') }}</label>
                             <input class="form-control" name="txn_id" id="transaction"
                                 placeholder="{{ __('Enter Your Transaction Number') }}" required />
                         </div>
                         <p>{!! PriceHelper::GatewayText('bank') !!}</p>
                     </div>
                     <div class="modal-footer">

                         @csrf
                         <input type="hidden" name="payment_method" value="Bank">
                         <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                         <input type="hidden" name="state_id"
                             value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                             class="state_id_setup">
                         <button class="btn btn-primary btn-sm" type="button"
                             data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                         <button class="btn btn-primary btn-sm"
                             type="submit"><span>{{ __('Checkout With Bank Transfer') }}</span></button>
                 </form>
             </div>
         </div>
     </div>
     </div>



      <!-- Modal Paytabs -->
      <div class="modal fade" id="paytabs" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ __('Transactions via Paytabs') }}</h6>
                    <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="card-body">

                        <form class="interactive-credit-card row" action="{{ route('front.paytab.submit') }}"
                            method="POST">
                            @csrf

                            <input type="hidden" name="payment_method" value="Paytabs">
                            <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                            <input type="hidden" name="state_id"
                                value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}"
                                class="state_id_setup">
                            <p class="p-3">{{ PriceHelper::GatewayText('paytabs') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="button"
                        data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                    <button class="btn btn-primary btn-sm"
                        type="submit"><span>{{ __('Chekout With Paytabs') }}</span></button>
                </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal Spaceremit -->
    <div class="modal fade" id="spaceremit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ __('Transactions via Spaceremit') }}</h6>
                    <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="card-body">
                        <form id="spaceremit-form" class="interactive-credit-card row" action="{{ route('front.checkout.submit') }}" method="POST">
                            @csrf
                            <input type="hidden" name="payment_method" value="Spaceremit">
                            <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                            <input type="hidden" name="state_id" value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}" class="state_id_setup">

                            @php $addr = Session::get('billing_address'); $gtotal = $grand_total ?? Session::get('grand_total_checkout'); @endphp
                            <input type="hidden" name="amount"   value="{{ $gtotal }}">
                            <input type="hidden" name="currency" value="{{ PriceHelper::setCurrencyName() }}">
                            <input type="hidden" name="fullname" value="{{ $addr['bill_first_name'] ?? '' }} {{ $addr['bill_last_name'] ?? '' }}">
                            <input type="hidden" name="email"    value="{{ $addr['bill_email'] ?? '' }}">
                            <input type="hidden" name="phone"    value="{{ $addr['bill_phone'] ?? '' }}">
                            <!-- Spaceremit payment method selectors -->
                            <div class="sp-one-type-select mb-2">
                                <input type="radio" name="sp-pay-type-radio" value="local-methods-pay" id="sp_local_methods_radio" checked>
                                <label for="sp_local_methods_radio"><div>{{ __('Local payment methods') }}</div></label>
                                <div id="spaceremit-local-methods-pay"></div>
                            </div>
                            <div class="sp-one-type-select mb-2">
                                <input type="radio" name="sp-pay-type-radio" value="card-pay" id="sp_card_radio">
                                <label for="sp_card_radio"><div>{{ __('Card payment') }}</div></label>
                                <div id="spaceremit-card-pay"></div>
                            </div>

                            <p class="p-3">{{ PriceHelper::GatewayText('spaceremit') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="button" data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                    <button class="btn btn-primary btn-sm" type="submit"><span>{{ __('Checkout With Spaceremit') }}</span></button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal DodoPayments -->
    <div class="modal fade" id="dodopayments" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ __('Transactions via DodoPayments') }}</h6>
                    <button class="close" type="button" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="card-body">
                        <form id="dodopayments-form" action="{{ route('front.checkout.submit') }}" method="POST">
                            @csrf
                            <input type="hidden" name="payment_method" value="DodoPayments">
                            <input type="hidden" name="shipping_id" value="" class="shipping_id_setup">
                            <input type="hidden" name="state_id" value="{{ auth()->check() && auth()->user()->state_id ? auth()->user()->state_id : '' }}" class="state_id_setup">
                            
                            <p>{{ PriceHelper::GatewayText('dodopayments') }}</p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                {{ __('Click the button below to open the payment popup with all available payment methods.') }}
                            </div>
                            
                            <!-- DodoPayments Overlay Checkout Container -->
                            <div id="dodopayments-checkout-container" style="display: none;">
                                <!-- Payment methods will be loaded here -->
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="button" data-bs-dismiss="modal"><span>{{ __('Cancel') }}</span></button>
                    <button class="btn btn-primary btn-sm" type="button" id="dodopayments-pay-btn"><span>{{ __('Pay with DodoPayments') }}</span></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var SP_PUBLIC_KEY = "{{ $spk['public_key'] ?? '' }}";
        var SP_FORM_ID = "#spaceremit-form";
        var SP_SELECT_RADIO_NAME = "sp-pay-type-radio";
        var LOCAL_METHODS_BOX_STATUS = true;
        var LOCAL_METHODS_PARENT_ID = "#spaceremit-local-methods-pay";
        var CARD_BOX_STATUS = true;
        var CARD_BOX_PARENT_ID = "#spaceremit-card-pay";
        var SP_FORM_AUTO_SUBMIT_WHEN_GET_CODE = true;
        function SP_SUCCESSFUL_PAYMENT(c){ console.log('sp success',c); }
        function SP_FAILD_PAYMENT(){ console.log('sp failed'); }
        function SP_RECIVED_MESSAGE(m){ console.log(m); }
        function SP_NEED_AUTH(u){ window.location.href = u; }
    </script>
    <script src="https://spaceremit.com/api/v2/js_script/spaceremit.js"></script>
    
    <!-- DodoPayments Overlay Checkout Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dodopaymentsPayBtn = document.getElementById('dodopayments-pay-btn');
            const dodopaymentsForm = document.getElementById('dodopayments-form');
            const checkoutContainer = document.getElementById('dodopayments-checkout-container');
            
            if (dodopaymentsPayBtn && dodopaymentsForm) {
                dodopaymentsPayBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    dodopaymentsPayBtn.disabled = true;
                    dodopaymentsPayBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                    
                    // Submit form via AJAX
                    const formData = new FormData(dodopaymentsForm);
                    
                    fetch(dodopaymentsForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('DodoPayments response:', data);
                        if (data.status && data.overlay_checkout) {
                            // Check if it's a mock payment for localhost
                            if (data.mock_payment) {
                                console.log('Using mock payment interface');
                                // For localhost, show a mock payment interface
                                showMockPaymentInterface(data.payment_id);
                            } else {
                                console.log('Using real DodoPayments overlay');
                                // Initialize DodoPayments overlay checkout
                                initializeDodoPaymentsOverlay(data.payment_id, data.api_key);
                            }
                        } else {
                            // Handle error
                            alert(data.message || 'Payment initialization failed');
                            resetButton();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing payment');
                        resetButton();
                    });
                });
            }
            
            function resetButton() {
                dodopaymentsPayBtn.disabled = false;
                dodopaymentsPayBtn.innerHTML = '<span>Pay with DodoPayments</span>';
            }
            
            function showMockPaymentInterface(paymentId) {
                // Create overlay for mock payment
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
                
                const modal = document.createElement('div');
                modal.style.cssText = `
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    max-width: 500px;
                    width: 90%;
                    text-align: center;
                `;
                
                modal.innerHTML = `
                    <h3>DodoPayments Mock Payment</h3>
                    <p>Payment ID: ${paymentId}</p>
                    <p>This is a mock payment interface for localhost development.</p>
                    <div style="margin: 20px 0;">
                        <button id="mock-success" style="background: #28a745; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 5px; cursor: pointer;">Simulate Success</button>
                        <button id="mock-failure" style="background: #dc3545; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 5px; cursor: pointer;">Simulate Failure</button>
                    </div>
                    <button id="mock-close" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Close</button>
                `;
                
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
                
                // Event listeners
                document.getElementById('mock-success').addEventListener('click', function() {
                    document.body.removeChild(overlay);
                    window.location.href = '{{ route("front.checkout.redirect") }}?payment_id=' + paymentId + '&status=success';
                });
                
                document.getElementById('mock-failure').addEventListener('click', function() {
                    document.body.removeChild(overlay);
                    alert('Payment failed. Please try again.');
                    resetButton();
                });
                
                document.getElementById('mock-close').addEventListener('click', function() {
                    document.body.removeChild(overlay);
                    resetButton();
                });
            }
            
            function initializeDodoPaymentsOverlay(paymentId, apiKey) {
                // Create overlay iframe
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
                
                // Listen for payment completion
                window.addEventListener('message', function(event) {
                    if (event.origin === 'https://checkout.dodopayments.com') {
                        if (event.data.type === 'payment_completed') {
                            document.body.removeChild(overlay);
                            window.location.href = '{{ route("front.checkout.redirect") }}?payment_id=' + event.data.payment_id + '&status=success';
                        } else if (event.data.type === 'payment_failed') {
                            document.body.removeChild(overlay);
                            alert('Payment failed. Please try again.');
                            resetButton();
                        }
                    }
                });
            }
        });
    </script>
@endonce