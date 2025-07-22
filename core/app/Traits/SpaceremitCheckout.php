<?php

namespace App\Traits;

use App\{
    Models\Setting,
    Models\PromoCode,
    Models\TrackOrder,
    Helpers\EmailHelper,
    Helpers\PriceHelper,
    Helpers\SmsHelper,
    Jobs\EmailSendJob,
    Models\Item,
    Models\Order,
    Models\ShippingService,
    Models\State,
    Models\Notification,
    Models\PaymentSetting,
};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

trait SpaceremitCheckout
{
    /**
     * Store API credentials for easy use
     */
    private $spaceremitPublic;
    private $spaceremitSecret;

    public function __spaceremitConstruct()
    {
        $data = PaymentSetting::whereUniqueKeyword('spaceremit')->first();
        if ($data) {
            $paydata = $data->convertJsonData();
            $this->spaceremitPublic = $paydata['public_key'] ?? null;
            $this->spaceremitSecret = $paydata['secret_key'] ?? null;
            // expose through config if caller wants
            Config::set('services.spaceremit.public_key', $this->spaceremitPublic);
            Config::set('services.spaceremit.secret_key', $this->spaceremitSecret);
        }
    }

    /**
     * Create a Spaceremit checkout session and return the redirect url
     */
    public function spaceremitSubmit($data)
    {
        // This method is called after the Spaceremit JS injected SP_payment_code
        if(!isset($data['SP_payment_code'])){
            return ['status'=>false,'message'=>'Payment code not found'];
        }

        $paymentCode = $data['SP_payment_code'];

        // Verify with Spaceremit server
        try{
            $response = Http::asJson()->post('https://spaceremit.com/api/v2/payment_info/',[
                'private_key' => $this->spaceremitSecret,
                'payment_id'  => $paymentCode,
            ])->json();

            if(($response['response_status']??null) !== 'success'){
                return ['status'=>false,'message'=>'Payment verification failed'];
            }
        }catch(\Exception $e){
            return ['status'=>false,'message'=>$e->getMessage()];
        }

        // Build order using existing logic
        return $this->spaceremitNotify(['transaction_id'=>$paymentCode]);
    }

    /**
     * Handle callback/webhook and create order if payment is successful.
     */
    public function spaceremitNotify($resData)
    {
        // Accept call when we already verified payment externally
        if (!isset($resData['transaction_id'])) {
            return ['status' => false, 'message' => 'Payment Failed'];
        }

        $cart = Session::get('cart');
        $user = Auth::user();
        $total_tax = 0;
        $cart_total = 0;
        $total = 0;
        $option_price = 0;

        foreach ($cart as $key => $items) {
            $total += $items['main_price'] * $items['qty'];
            $option_price += $items['attribute_price'];
            $cart_total = $total + $option_price;
            $item = Item::findOrFail($key);
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item) * $items['qty'];
            }
        }

        $order_input_data = Session::get('order_input_data');
        if (!PriceHelper::Digital()) {
            $shipping = null;
        } else {
            $shipping = ShippingService::findOrFail($order_input_data['shipping_id']);
        }

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $grand_total += PriceHelper::StatePrce($order_input_data['state_id'], $cart_total);
        $total_amount = PriceHelper::setConvertPrice($grand_total);

        $orderData = Session::get('order_data');
        $orderData['txnid'] = $resData['transaction_id'] ?? ($resData['reference'] ?? Str::random(8));
        $orderData['payment_status'] = 'Paid';

        $order = Order::create($orderData);
        $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
        $order->transaction_number = $new_txn;
        $order->save();

        // update stock, send mails etc (same as other gateways)
        PriceHelper::Transaction($order->id, $order->transaction_number, EmailHelper::getEmail(), PriceHelper::OrderTotal($order, 'trns'));
        PriceHelper::LicenseQtyDecrese($cart);
        if ($discount) {
            $coupon_id = $discount['code']['id'];
            $get_coupon = PromoCode::findOrFail($coupon_id);
            $get_coupon->no_of_times -= 1;
            $get_coupon->update();
        }

        TrackOrder::create([
            'title' => 'Pending',
            'order_id' => $order->id,
        ]);

        Notification::create(['order_id' => $order->id]);

        $setting = Setting::first();
        if ($setting->is_twilio == 1) {
            $sms = new SmsHelper();
            $user_number = json_decode($order->billing_info, true)['bill_phone'];
            if ($user_number) {
                $sms->SendSms($user_number, "'purchase'", $order->transaction_number);
            }
        }

        $emailData = [
            'to' => EmailHelper::getEmail(),
            'type' => 'Order',
            'user_name' => isset($user) ? $user->displayName() : Session::get('billing_address')['bill_first_name'],
            'order_cost' => $total_amount,
            'transaction_number' => $order->transaction_number,
            'site_title' => Setting::first()->title,
        ];

        if ($setting->is_queue_enabled == 1) {
            dispatch(new EmailSendJob($emailData, 'template'));
        } else {
            $email = new EmailHelper();
            $email->sendTemplateMail($emailData, 'template');
        }

        Session::put('order_id', $order->id);
        Session::forget('cart');
        Session::forget('discount');
        Session::forget('order_data');
        Session::forget('order_payment_id');
        Session::forget('coupon');

        return ['status' => true];
    }

    /**
     * Route hit by Spaceremit webhook
     */
    public function spaceremitCallback(Request $request)
    {
        $payment = $this->spaceremitNotify($request->all());
        if ($payment['status']) {
            return redirect()->route('front.checkout.success');
        }
        Session::put('message', $payment['message']);
        return redirect()->route('front.checkout.cancle');
    }
} 