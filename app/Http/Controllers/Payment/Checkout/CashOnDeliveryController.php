<?php

namespace App\Http\Controllers\Payment\Checkout;

use App\{
    Models\Cart,
    Models\Order,
    Classes\GeniusMailer,
    Jobs\ShippedToDelivery
};
use App\Helpers\PriceHelper;
use App\Models\Country;
use App\Models\Package;
use App\Models\Reward;
use App\Models\Shipping;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Session;
use OrderHelper;

use Illuminate\Support\Str;

class CashOnDeliveryController extends CheckoutBaseControlller
{
    public function store(Request $request)
    {
    //  dd($request->billingAddress);
        $input = $request->all();

        if ($request->pass_check) {
           
            $auth = OrderHelper::auth_check($input); // For Authentication Checking
            if (!$auth['auth_success']) {
                dd('ok');
                return redirect()->back()->with('unsuccess', $auth['error_message']);
            }
        }

        if (!Session::has('cart')) {


            return redirect()->route('front.cart')->with('success', __("You don't have any product to checkout."));
        }
            $totalQuantity = 0;
           $oldCart = Session::get('cart');
           
           // $cart = new Cart($oldCart);
           $cart = Cart::restoreCart($oldCart);
           OrderHelper::license_check($cart); // For License Checking
           $t_oldCart = Session::get('cart');
           $t_cart = Cart::restoreCart($t_oldCart);
           $products = $t_cart->items;
           foreach ($products as $key => $value) {
            $totalQuantity += $value['qty'];
            }
          // $t_cart = new Cart($t_oldCart);
          
        $new_cart = [];
        $new_cart['totalQty'] = $totalQuantity;
        $new_cart['totalPrice'] = $t_cart->totalPrice;
        $new_cart['items'] = $t_cart->items;
        $new_cart = json_encode($new_cart);
        $temp_affilate_users = OrderHelper::product_affilate_check($cart); // For Product Based Affilate Checking
        $affilate_users = $temp_affilate_users == null ? null : json_encode($temp_affilate_users);


        // $orderCalculate = PriceHelper::getOrderTotal($input, $cart);
        // if (isset($orderCalculate['success']) && $orderCalculate['success'] == false) {
        // dd('okbnn');

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
        //     // $input['shipping_cost'] = $packeing->price;

        //     $input['shipping_cost'] = $input['shipping_cost'];

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
        //     // $shipping_cost = $orderCalculate['shipping_cost'];
        //     $shipping_cost =$input['shipping_cost'];

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



        $orderTotal = $t_cart->totalPrice  + $input['shippingCost']  - $input['coupon_discount'] - $input['refferal_discount'];
        
        $order = new Order;
       

        $success_url = route('front.payment.return');
        // $success_url=route('user-orders') ;
        $input['user_id'] = Auth::check() ? Auth::user()->id : NULL;
        $input['cart'] = $new_cart;
        $input['totalQty'] = $totalQuantity;
        $input['billing_address_id'] = $request->billingAddress ?? null;
        $input['shipping_address_id'] = $request->shippingAddress ?? null;
        $input['method'] = $request->selected_payment_method ?? null;
        $input['coupon_discount'] = $request->coupon_discount ?? 0;
        $input['shipping_cost'] = $request->shippingCost ?? 0;

        $input['affilate_users'] = $affilate_users ??  Auth::user()->affiliated_by;
        $input['pay_amount'] = $orderTotal ;
        $input['order_number'] = Str::random(4) . time();
        $input['wallet_price'] = $request->wallet_price / $this->curr->value;
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
        //     dd($input);
        //     $input['tax_location'] = Country::findOrFail($input['tax'])->country_name;
        // }
        // $input['tax'] = Session::get('current_tax');

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
               // $user = OrderHelper::affilate_check(Session::get('refferel_user_id'), $sub, $input['dp']); // For Affiliate Checking
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
                // $user = OrderHelper::affilate_check(Session::get('affilate'), $sub, $input['dp']); // For Affiliate Checking
                $input['affilate_user'] = Session::get('affilate');
                $input['affilate_charge'] = $sub;
            }
            Session::forget('affilate');
        }   
          
       
        $order->fill($input)->save();
        $order->tracks()->create(['title' => 'Pending', 'text' => 'You have successfully placed your order.']);
        $order->notifications()->create();

        // ShippedToDelivery::dispatch($input['order_number']);


        if ($input['coupon_code'] != "") {
            OrderHelper::coupon_check($input['coupon_code']); // For Coupon Checking
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

        // $mailer = new GeniusMailer();

        // $mailer->sendAutoOrderMail($data, $order->id);

        //Sending Email To Admin

        $data = [
            'to' => $this->ps->contact_email,
            'subject' => "New Order Recieved!!",
            'body' => "Hello Admin!<br>Your store has received a new order.<br>Order Number is " . $order->order_number . ".Please login to your panel to check. <br>Thank you.",
        ];
        $mailer = new GeniusMailer();

        $mailer->sendCustomMail($data);
        return redirect($success_url);
    }
}
