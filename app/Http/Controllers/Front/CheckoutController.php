<?php

namespace App\Http\Controllers\Front;

use App\{
    Models\Cart,
    Models\Order,
    Models\PaymentGateway,
    Models\User,

};
use App\Models\City;
use App\Models\State;
use DB;
use Auth;
use Illuminate\Http\Request;
use Session;


class CheckoutController extends FrontBaseController
{
    // Loading Payment Gateways

    public function loadpayment($slug1, $slug2)
    {
        $curr = $this->curr;
        $payment = $slug1;
        $pay_id = $slug2;
        $gateway = '';
        if ($pay_id != 0) {
            $gateway = PaymentGateway::findOrFail($pay_id);
        }
        return view('load.payment', compact('payment', 'pay_id', 'gateway', 'curr'));
    }

    // Wallet Amount Checking

    public function walletcheck()
    {
        $amount = (float)$_GET['code'];
        $total = (float)$_GET['total'];
        $balance = Auth::user()->balance;
        if ($amount <= $balance) {
            if ($amount > 0 && $amount <= $total) {
                $total -= $amount;
                $data[0] = $total;
                $data[1] = $amount;
                $data[2] = \PriceHelper::showCurrencyPrice($total);
                $data[3] = \PriceHelper::showCurrencyPrice($amount);
                return response()->json($data);
            } else {
                return response()->json(0);
            }
        } else {
            return response()->json(0);
        }
    }

    public function checkout1()
    {
        if(isset($_GET['remove_coupon'])){
            Session::forget('already') ? Session::forget('already') : null;
            Session::forget('coupon');
            Session::forget('coupon_code');
            Session::forget('coupon_id');
            Session::forget('coupon_total1');
            Session::forget('coupon_percentage');
        }

        if (!Session::has('cart')) {
            return redirect()->route('front.cart')->with('success', __("You don't have any product to checkout."));
        }
        $dp = 1;
        $vendor_shipping_id = 0;
        $vendor_packing_id = 0;
        $curr = $this->curr;
        $gateways =  PaymentGateway::scopeHasGateway($this->curr->id);
        // dd($gateways);
        $pickups =  DB::table('pickups')->get();
        $oldCart = Session::get('cart');
        // $cart = new Cart($oldCart);

        $cart= $oldCart;
       
        $products = $cart->items;
        $paystack = PaymentGateway::whereKeyword('paystack')->first();
        $paystackData = $paystack->convertAutoData();
        // $voguepay = PaymentGateway::whereKeyword('voguepay')->first();
        // $voguepayData = $voguepay->convertAutoData();
        // If a user is Authenticated then there is no problm user can go for checkout
      
        if (Auth::check()) {
       
           
            
            $total = $cart->totalPrice;
            $coupon = Session::has('coupon') ? Session::get('coupon') : 0;

            if (!Session::has('coupon_total')) {
                $total = $total - $coupon;
                $total = $total + 0;
                dd($total);
            } else {
                $total = Session::get('coupon_total');
                $total =  str_replace(',', '', str_replace($curr->sign, '', $total));
            }
            $orderCount = Order::where('user_id', Auth::user()->id)->count();
           
            // $orderCompleted = Order::where('user_id', Auth::user()->id)->where('status', 'completed')->count();
           
            if ($orderCount == 0) {
                $user = User::where('id', Auth::user()->id)->select('reffered_by')->first();
                dd($user);
                if ($user && $user->reffered_by) {
                    $refferal_discount = $total * ($this->gs->referral_bonus / 100);
                    $total = $total - ($total * ($this->gs->referral_bonus / 100));
                }else{
                    $refferal_discount = 0;
                }
            }
            else{
                $refferal_discount = 0;
            }

            // dd($cart->items);
            return view('frontend.checkout', ['products' => $cart->items, 'refferal_discount'=>$refferal_discount,'totalPrice' => $total, 'pickups' => $pickups, 'totalQty' => $cart->totalQty, 'gateways' => $gateways, 'shipping_cost' => 0, 'digital' => $dp, 'curr' => $curr,  'vendor_shipping_id' => $vendor_shipping_id, 'vendor_packing_id' => $vendor_packing_id, 'paystack' => $paystackData]);
        } 
        
        
        // else {
        //     if ($this->gs->guest_checkout == 1) {
        //         if ($this->gs->multiple_shipping == 1) {
        //             $ship_data = Order::getShipData($cart);
        //             $shipping_data = $ship_data['shipping_data'];
        //             $vendor_shipping_id = $ship_data['vendor_shipping_id'];
        //         } else {
        //             $shipping_data  = DB::table('shippings')->where('user_id', '=', 0)->get();
        //         }
        //         // Packaging
        //         if ($this->gs->multiple_shipping == 1) {
        //             $pack_data = Order::getPackingData($cart);
        //             $package_data = $pack_data['package_data'];
        //             $vendor_packing_id = $pack_data['vendor_packing_id'];
        //         } else {
        //             $package_data  = DB::table('packages')->whereUserId('0')->get();
                    
        //         }
               


        //         foreach ($products as $prod) {
        //             if ($prod['item']['type'] == 'Physical') {
        //                 $dp = 0;
        //                 break;
        //             }
        //         }
        //         $total = $cart->totalPrice;
        //         $coupon = Session::has('coupon') ? Session::get('coupon') : 0;

        //         if (!Session::has('coupon_total')) {
        //             $total = $total - $coupon;
        //             $total = $total + 0;
        //         } else {
        //             $total = Session::get('coupon_total');
        //             $total =  str_replace($curr->sign, '', $total) + round(0 * $curr->value, 2);
        //         }
        //         foreach ($products as $prod) {
        //             if ($prod['item']['type'] != 'Physical') {
        //                 if (!Auth::check()) {
        //                     $ck = 1;
        //                     return view('frontend.checkout', ['products' => $cart->items, 'totalPrice' => $total, 'pickups' => $pickups, 'totalQty' => $cart->totalQty, 'gateways' => $gateways, 'shipping_cost' => 0, 'digital' => $dp, 'curr' => $curr, 'shipping_data' => $shipping_data, 'package_data' => $package_data, 'vendor_shipping_id' => $vendor_shipping_id, 'vendor_packing_id' => $vendor_packing_id, 'paystack' => $paystackData]);
        //                 }
        //             }
        //         }
        //         return view('frontend.checkout', ['products' => $cart->items, 'totalPrice' => $total, 'pickups' => $pickups, 'totalQty' => $cart->totalQty, 'gateways' => $gateways, 'shipping_cost' => 0, 'digital' => $dp, 'curr' => $curr, 'shipping_data' => $shipping_data, 'package_data' => $package_data, 'vendor_shipping_id' => $vendor_shipping_id, 'vendor_packing_id' => $vendor_packing_id, 'paystack' => $paystackData]);
        //     }

        //     // If guest checkout is Deactivated then display pop up form with proper error message

        //     else {
               
        //         // Shipping Method

        //         if ($this->gs->multiple_shipping == 1) {
        //             $ship_data = Order::getShipData($cart);
        //             $shipping_data = $ship_data['shipping_data'];
        //             $vendor_shipping_id = $ship_data['vendor_shipping_id'];
        //         } else {
        //             $shipping_data  = DB::table('shippings')->where('user_id', '=', 0)->get();
             
                
        //         }

        //         // Packaging

        //         if ($this->gs->multiple_packaging == 1) {
        //             $pack_data = Order::getPackingData($cart);
        //             $package_data = $pack_data['package_data'];
        //             $vendor_packing_id = $pack_data['vendor_packing_id'];
        //         } else {
        //             $package_data  = DB::table('packages')->where('user_id', '=', 0)->get();
        //         }

        //         $total = $cart->totalPrice;

               
        //         $coupon = Session::has('coupon') ? Session::get('coupon') : 0;

        //         if (!Session::has('coupon_total')) {
        //             $total = $total - $coupon;
        //             $total = $total + 0;
        //         } else {
        //             $total = Session::get('coupon_total');
        //             $total = $total;
        //         }

              
        //         $ck = 1;
        //         return view('frontend.checkout', ['products' => $cart->items, 'totalPrice' => $total, 'pickups' => $pickups, 'totalQty' => $cart->totalQty, 'gateways' => $gateways, 'shipping_cost' => 0, 'digital' => $dp, 'curr' => $curr, 'shipping_data' => $shipping_data, 'package_data' => $package_data, 'vendor_shipping_id' => $vendor_shipping_id, 'vendor_packing_id' => $vendor_packing_id, 'paystack' => $paystackData]);
        //     }
        // }
    
    }

    public function checkout()
    {
        if (isset($_GET['remove_coupon'])) {
            Session::forget('already');
            Session::forget('coupon');
            Session::forget('coupon_code');
            Session::forget('coupon_id');
            Session::forget('coupon_total1');
            Session::forget('coupon_percentage');
        }
    
        if (!Session::has('cart')) {
            return redirect()->route('front.cart')->with('success', __("You don't have any product to checkout."));
        }
        
      

        $dp = 1;
        $vendor_shipping_id = 0;
        $vendor_packing_id = 0;
        $curr = $this->curr;
        $totalQuantity = 0;
        $gateways = PaymentGateway::scopeHasGateway($this->curr->id);
        $pickups = DB::table('pickups')->get();
        $cart = Session::get('cart');
       
        $products = $cart->items;
        $paystack = PaymentGateway::whereKeyword('paystack')->first();
        $paystackData = $paystack ? $paystack->convertAutoData() : null;
    

        foreach ($products as $key => $value) {
        $totalQuantity += $value['qty'];
        }

       
        if (Auth::check()) {
            $total = $cart->totalPrice;
            // dd($total);
            $coupon = Session::has('coupon') ? Session::get('coupon') : 0;
    
            if (!Session::has('coupon_total')) {
                $total = $total - $coupon;
            } else {
                $total = preg_replace('/[^0-9.]/', '', Session::get('coupon_total'));
                $total = (float)$total;
            }
    
            $refferal_discount = 0;
            $user = Auth::user();
            $orderCount = Order::where('user_id', $user->id)->count();
            
    
            if ($orderCount == 0 && $user->reffered_by) {
                
                $refferal_discount = $total * ($this->gs->referral_bonus / 100);
                // dd($refferal_discount);
                $total -= $refferal_discount;
            }
//    dd   ($refferal_discount);
            return view('frontend.checkout', [
                'products' => $products,
                'refferal_discount' => $refferal_discount,
                'totalPrice' => $total,
                'pickups' => $pickups,
                'totalQty' => $totalQuantity,
                'gateways' => $gateways,
                'shipping_cost' => 0,
                'digital' => $dp,
                'curr' => $curr,
                'vendor_shipping_id' => $vendor_shipping_id,
                'vendor_packing_id' => $vendor_packing_id,
                'paystack' => $paystackData
            ]);
        }
    
        // [Optional: Add guest user checkout logic if needed]
    
        return redirect()->route('user.login')->with('error', 'Please login to proceed to checkout.');
    }
    
    public function getState($country_id)
    {

        $states = State::where('country_id', $country_id)->get();

        if (Auth::user()) {
            $user_state = Auth::user()->state;
        } else {
            $user_state = 0;
        }


        $html_states = '<option value="" > Select State </option>';
        foreach ($states as $state) {
            if ($state->id == $user_state) {
                $check = 'selected';
            } else {

                $check = '';
            }
            $html_states .= '<option value="' . $state->id . '"   rel="' . $state->country->id . '" ' . $check . ' >' . $state->state . '</option>';
        }

        return response()->json(["data" => $html_states, "state" => $user_state]);
    }

    public function getCity(Request $request)
    {

        $cities = City::where('state_id', $request->state_id)->get();

        if (Auth::user()) {
            $user_city = Auth::user()->city;
        } else {
            $user_city = 0;
        }

        $html_cities = '<option value="" > Select City </option>';
        foreach ($cities as $city) {
            if ($city->id == $user_city) {
                $check = 'selected';
            } else {
                $check = '';
            }
            $html_cities .= '<option value="' . $city->city_name . '"   ' . $check . ' >' . $city->city_name . '</option>';
        }

        return response()->json(["data" => $html_cities, "state" => $user_city]);
    }


    // Redirect To Checkout Page If Payment is Cancelled

    public function paycancle()
    {

        return redirect()->route('front.checkout')->with('unsuccess', __('Payment Cancelled.'));
    }


    // Redirect To Success Page If Payment is Comleted

    public function payreturn()
    {
        if (Session::has('tempcart')) {
            $oldCart = Session::get('tempcart');
            // $tempcart = new Cart($oldCart);
            $tempcart =$oldCart;
            // dd($tempcart);
            $order = Session::get('temporder');
        } else {
            $tempcart = '';
            return redirect()->back();
        }
        return view('frontend.success', compact('tempcart', 'order'));
    }
}




