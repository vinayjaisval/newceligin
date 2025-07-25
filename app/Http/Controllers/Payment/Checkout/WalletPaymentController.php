<?php

namespace App\Http\Controllers\Payment\Checkout;

use App\{
    Models\Cart,
    Models\Order,
    Classes\GeniusMailer
};
use App\Helpers\OrderHelper;
use App\Helpers\PriceHelper;
use App\Models\Country;
use App\Models\Reward;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class WalletPaymentController extends CheckoutBaseControlller
{
    public function store(Request $request)
    {



        $input = $request->all();
        if ($request->pass_check) {
            $auth = OrderHelper::auth_check($input); // For Authentication Checking
            if (!$auth['auth_success']) {
                return redirect()->back()->with('unsuccess', $auth['error_message']);
            }
        }

        if (!Session::has('cart')) {
            return redirect()->route('front.cart')->with('success', __("You don't have any product to checkout."));
        }


        $oldCart = Session::get('cart');
        $cart = Cart::restoreCart($oldCart);
        //    dd($cart);
        // $orderCalculate = PriceHelper::getOrderTotal($input, $cart);
      

        if (!Auth::check()) {
            return redirect()->back()->with('unsuccess', 'Please login to continue');
        } else {
            $user = Auth::user();
            // $wbalance = $user->affilate_income + $user->referral_income;
           
            if ($user->balance < $input['total']) {
               
                return redirect()->back()->with('unsuccess', 'You do not have enough balance in your wallet');
            }
        }


        OrderHelper::license_check($cart); // For License Checking
        $t_oldCart = Session::get('cart');
     
        $t_cart = Cart::restoreCart($t_oldCart);
        // $t_cart = new Cart($t_oldCart);
        $new_cart = [];
        $new_cart['totalQty'] = $t_cart->totalQty;
        $new_cart['totalPrice'] = $t_cart->totalPrice;
        $new_cart['items'] = $t_cart->items;
        $new_cart = json_encode($new_cart);
        $temp_affilate_users = OrderHelper::product_affilate_check($cart); // For Product Based Affilate Checking
        $affilate_users = $temp_affilate_users == null ? null : json_encode($temp_affilate_users);


        // if (isset($orderCalculate['success']) && $orderCalculate['success'] == false) {
        //     return redirect()->back()->with('unsuccess', $orderCalculate['message']);
        // }

        // if ($this->gs->multiple_shipping == 0) {
        //     $orderTotal = $orderCalculate['total_amount'];
        //     $shipping = $orderCalculate['shipping'];
        //     $packeing = $orderCalculate['packeing'];
        //     $is_shipping = $orderCalculate['is_shipping'];
        //     $vendor_shipping_ids = $orderCalculate['vendor_shipping_ids'];
        //     $vendor_packing_ids = $orderCalculate['vendor_packing_ids'];
        //     $vendor_ids = $orderCalculate['vendor_ids'];

        //     $input['shipping_title'] = $shipping->title;
        //     $input['vendor_shipping_id'] = $shipping->id;
        //     $input['packing_title'] = $packeing->title;
        //     $input['vendor_packing_id'] = $packeing->id;
        //     $input['shipping_cost'] = $packeing->price;
        //     $input['packing_cost'] = $packeing->price;
        //     $input['is_shipping'] = $is_shipping;
        //     $input['vendor_shipping_ids'] = $vendor_shipping_ids;
        //     $input['vendor_packing_ids'] = $vendor_packing_ids;
        //     $input['vendor_ids'] = $vendor_ids;
        // } else {


        //     // multi shipping

        //     $orderTotal = $orderCalculate['total_amount'];
        //     $shipping = $orderCalculate['shipping'];
        //     $packeing = $orderCalculate['packeing'];
        //     $is_shipping = $orderCalculate['is_shipping'];
        //     $vendor_shipping_ids = $orderCalculate['vendor_shipping_ids'];
        //     $vendor_packing_ids = $orderCalculate['vendor_packing_ids'];
        //     $vendor_ids = $orderCalculate['vendor_ids'];
        //     $shipping_cost = $orderCalculate['shipping_cost'];
        //     $packing_cost = $orderCalculate['packing_cost'];

        //     $input['shipping_title'] = $vendor_shipping_ids;
        //     $input['vendor_shipping_id'] = $vendor_shipping_ids;
        //     $input['packing_title'] = $vendor_packing_ids;
        //     $input['vendor_packing_id'] = $vendor_packing_ids;
        //     $input['shipping_cost'] = $shipping_cost;
        //     $input['packing_cost'] = $packing_cost;
        //     $input['is_shipping'] = $is_shipping;
        //     $input['vendor_shipping_ids'] = $vendor_shipping_ids;
        //     $input['vendor_packing_ids'] = $vendor_packing_ids;
        //     $input['vendor_ids'] = $vendor_ids;
        //     unset($input['shipping']);
        //     unset($input['packeging']);
        // }
        $orderTotal = $t_cart->totalPrice  + $input['shippingCost']  - $input['coupon_discount'];

        $order = new Order;
        
        $success_url = route('front.payment.return');
        $input['user_id'] = Auth::check() ? Auth::user()->id : NULL;
        $input['affilate_users'] = $affilate_users ??  Auth::user()->affiliated_by;
        $input['cart'] = $new_cart;
        $input['pay_amount'] = $orderTotal / $this->curr->value;
        $input['order_number'] = Str::random(4) . time();
        $input['wallet_price'] = $request->wallet_price / $this->curr->value;
        $input['method'] = "Wallet";
        $input['payment_status'] = "Completed";
        if($request->refferal_discount){
            $input['refferal_discount']=$request->refferal_discount;
        }
        $tax = 0;
        foreach($cart->items as $data){
            $tax += isset($data['price']) && isset($data['item']['product_tax']) ? $data['price'] * $data['item']['product_tax'] / 100 : 0;
        }
        $input['tax'] = $tax;
       
        // if ($input['tax_type'] == 'state_tax') {
        //     $input['tax_location'] = State::findOrFail($input['tax'])->state;
        // } else {
        //     $input['tax_location'] = Country::findOrFail($input['tax'])->country_name;
        // }

        // $input['tax'] = Session::get('current_tax');

        // $input['tax'] = Session::get('current_tax');

        if ($input['dp'] == 1) {
            $input['status'] = 'completed';
        }


        if (Session::has('refferel_user_id')) {
            $val =  preg_replace('/\D/', '',$request->total) / $this->curr->value;
            $val = $val / 100;
            $sub = $val * $this->gs->affilate_charge;
            if ($temp_affilate_users != null) {
                $t_sub = 0;
                foreach ($temp_affilate_users as $t_cost) {
                    $t_sub += $t_cost['charge'];
                }
                $sub = $sub - $t_sub;
            }
            if ($sub > 0) {
                $user = OrderHelper::affilate_check(Session::get('refferel_user_id'), $sub, $input['dp']); // For Affiliate Checking
                $input['affilate_user'] = Session::get('refferel_user_id');
                $input['affilate_charge'] = $sub;
            }
            Session::forget('refferel_user_id');
        }

        if (Session::has('affilate')) {
            $val = $request->total / $this->curr->value;
            $val = $val / 100;
            $sub = $val * $this->gs->affilate_charge;
            if ($temp_affilate_users != null) {
                $t_sub = 0;
                foreach ($temp_affilate_users as $t_cost) {
                    $t_sub += $t_cost['charge'];
                }
                $sub = $sub - $t_sub;
            }
            if ($sub > 0) {
                $user = OrderHelper::affilate_check(Session::get('affilate'), $sub, $input['dp']); // For Affiliate Checking
                $input['affilate_user'] = Session::get('affilate');
                $input['affilate_charge'] = $sub;
            }
        }

        $order->fill($input)->save();
        $order->tracks()->create(['title' => 'Pending', 'text' => 'You have successfully placed your order.']);
        $order->notifications()->create();

        if ($input['coupon_id'] != "") {
            OrderHelper::coupon_check($input['coupon_id']); // For Coupon Checking
        }

        if (Auth::check()) {
            if ($this->gs->is_reward == 1) {
                $num = $order->pay_amount;
                $rewards = Reward::get();
                foreach ($rewards as $i) {
                    $smallest[$i->order_amount] = abs($i->order_amount - $num);
                }

                if(isset($smallest)){
                    asort($smallest);
              $final_reword = Reward::where('order_amount', key($smallest))->first();
              Auth::user()->update(['reward' => (Auth::user()->reward + $final_reword->reward)]);
              }
            }
        }

        OrderHelper::size_qty_check($cart); // For Size Quantiy Checking
        OrderHelper::stock_check($cart); // For Stock Checking
        OrderHelper::vendor_order_check($cart, $order); // For Vendor Order Checking

        Session::put('temporder', $order);
        Session::put('tempcart', $cart);
        Session::forget('cart');
        Session::forget('already');
        Session::forget('coupon');
        Session::forget('coupon_total');
        Session::forget('coupon_total1');
        Session::forget('coupon_percentage');
// dd($orderTotal);
        // Wallet Payment Logic
        $user = Auth::user();
        // $wbalance = $user->affilate_income + $user->referral_income;
        $user->balance = $user->balance - $orderTotal;
        // dd($user->balance);
        $user->save();
        

        if ($order->user_id != 0 && $order->wallet_price != 0) {
            OrderHelper::add_to_transaction($order, $order->wallet_price); // Store To Transactions
        }

        //Sending Email To Buyer
        $data = [
            'to' => $order->customer_email,
            'type' => "new_order",
            'cname' => $order->customer_name,
            'oamount' => "",
            'aname' => "",
            'aemail' => "",
            'wtitle' => "",
            'onumber' => $order->order_number,
        ];

        $mailer = new GeniusMailer();
        $mailer->sendAutoOrderMail($data, $order->id);

        //Sending Email To Admin
        $data = [
            'to' => $this->ps->contact_email,
            'subject' => "New Order Recieved!!",
            'body' => "Hello Admin!<br>Your store has received a new order.<br>Order Number is " . $order->order_number . ".Please login to your panel to check. <br>Thank you."
        ];
        $mailer = new GeniusMailer();
        $mailer->sendCustomMail($data);

        return redirect($success_url);
    }
}
