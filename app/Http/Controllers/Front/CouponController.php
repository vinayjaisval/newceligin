<?php

namespace App\Http\Controllers\Front;

use App\{
    Models\Cart,
    Models\Coupon,
    Models\Order
};
use App\Models\Product;
use Session;
use Illuminate\Http\Request;

class CouponController extends FrontBaseController
{

    public function coupon()
    {
        $gs = $this->gs;
        $code = $_GET['code'];
        $total = (float)preg_replace('/[^0-9\.]/ui', '', $_GET['total']);;
        $fnd = Coupon::where('code', '=', $code)->get()->count();
        $coupon = Coupon::where('code', '=', $code)->first();

        $cart = Session::get('cart');
        foreach ($cart->items as $item) {
            $product = Product::findOrFail($item['item']['id']);

            if ($coupon->coupon_type == 'category') {

                if ($product->category_id == $coupon->category) {
                    $coupon_check_type[] = 1;
                } else {

                    $coupon_check_type[] = 0;
                }
            } elseif ($coupon->coupon_type == 'sub_category') {
                if ($product->subcategory_id == $coupon->sub_category) {
                    $coupon_check_type[] = 1;
                } else {
                    $coupon_check_type[] = 0;
                }
            } elseif ($coupon->coupon_type == 'child_category') {
                if ($product->childcategory_id == $coupon->child_category) {
                    $coupon_check_type[] = 1;
                } else {
                    $coupon_check_type[] = 0;
                }
            } else {

                $coupon_check_type[] = 0;
            }
        }



        if (in_array(0, $coupon_check_type)) {
            return response()->json(0);
        }




        if ($fnd < 1) {
            return response()->json(0);
        } else {
            $coupon = Coupon::where('code', '=', $code)->first();
            $curr = $this->curr;
            if ($coupon->times != null) {
                if ($coupon->times == "0") {
                    return response()->json(0);
                }
            }
            $today = date('Y-m-d');
            $from = date('Y-m-d', strtotime($coupon->start_date));
            $to = date('Y-m-d', strtotime($coupon->end_date));
            if ($from <= $today && $to >= $today) {
                if ($coupon->status == 1) {
                    $oldCart = Session::has('cart') ? Session::get('cart') : null;
                    $val = Session::has('already') ? Session::get('already') : null;
                    if ($val == $code) {
                        return response()->json(2);
                    }
                    // $cart = new Cart($oldCart);
                    $cart = Cart::restoreCart($oldCart);

                    if ($coupon->type == 0) {
                        if ($coupon->price >= $total) {
                            return response()->json(3);
                        }
                        Session::put('already', $code);
                        $coupon->price = (int)$coupon->price;
                        $val = $total / 100;
                        $sub = $val * $coupon->price;
                        $total = $total - $sub;
                        $data[0] = \PriceHelper::showCurrencyPrice($total);
                        $data[1] = $code;
                        $data[2] = round($sub, 2);
                        Session::put('coupon', $data[2]);
                        Session::put('coupon_code', $code);
                        Session::put('coupon_id', $coupon->id);
                        Session::put('coupon_total', $data[0]);
                        $data[3] = $coupon->id;
                        $data[4] = $coupon->price . "%";
                        $data[5] = 1;

                        Session::put('coupon_percentage', $data[4]);

                        return response()->json($data);
                    } else {
                        if ($coupon->price >= $total) {
                            return response()->json(3);
                        }
                        Session::put('already', $code);
                        $total = $total - round($coupon->price * $curr->value, 2);
                        $data[0] = $total;
                        $data[1] = $code;
                        $data[2] = $coupon->price * $curr->value;
                        Session::put('coupon', $data[2]);
                        Session::put('coupon_code', $code);
                        Session::put('coupon_id', $coupon->id);
                        Session::put('coupon_total', $data[0]);
                        $data[3] = $coupon->id;
                        $data[4] = \PriceHelper::showCurrencyPrice($data[2]);
                        $data[0] = \PriceHelper::showCurrencyPrice($data[0]);
                        Session::put('coupon_percentage', 0);
                        $data[5] = 1;
                        return response()->json($data);
                    }
                } else {
                    return response()->json(0);
                }
            } else {
                return response()->json(0);
            }
        }
    }


    public function couponcheck()
    {
        $code = $_GET['code'];
        
        // $coupon = Coupon::where('code', '=', $code)->first();
        $user = auth()->user();
        if ($user == null) {

            $coupon = Coupon::where('code', '=', $code)->first();
        } else {

            $coupon = Coupon::where('code', '=', $code)->first();
             

            $couponUsed = Order::where('coupon_code', $code)->where('user_id', $user->id)->exists();
            if ($couponUsed) {
               
                return response()->json(8);
            }
        }

        // dd($user);
        // $code = $_GET['code'];

        // $coupon = Coupon::where('code', '=', $code)->first();

        // $couponUsed = Order::where('coupon_code', $code)->where('user_id', $user->id)->exists();

        // dd($_GET['applied_coupon']);
        if (isset($_GET['applied_coupon'])) {

            Session::forget('already');
            Session::forget('coupon');
            Session::forget('coupon_code');
            Session::forget('coupon_id');
            Session::forget('coupon_total1');
            $total = preg_replace('/[^0-9.]/', '', $_GET['total']) + (float)($_GET['coupon_discount']+$_GET['shipping_cost']);
            return response()->json(['total' => "₹" . $total, 'remove_coupen' => true]);
        }

        if (!$coupon) {
           
            return response()->json(0);
        }
        // dd($coupon);
        $cart = Session::get('cart');
        $discount_items = [];
        foreach ($cart->items as $key => $item) {
            $product = Product::findOrFail($item['item']['id']);
            if ($coupon->coupon_type == 'category') {
                if ($product->category_id == $coupon->category) {
                    $discount_items[] = $key;
                }
            } elseif ($coupon->coupon_type == 'sub_category') {
                if ($product->sub_category == $coupon->sub_category) {
                    $discount_items[] = $key;
                }
            } elseif ($coupon->coupon_type == 'child_category') {
                if ($product->child_category == $coupon->child_category) {
                    $discount_items[] = $key;
                }
            }
        }

        if (count($discount_items) == 0) {
            return 0;
        }

        //dd($discount_items);
        $main_discount_price = 0;
        foreach ($cart->items as $ckey => $cproduct) {
            if (in_array($ckey, $discount_items)) {
                $main_discount_price += $cproduct['price'];
            }
        }


        $total = (float)preg_replace('/[^0-9\.]/ui', '', $main_discount_price);
        $fnd = Coupon::where('code', '=', $code)->get()->count();
        // if (Session::has('is_tax')) {
        //     $xtotal = ($total * Session::get('is_tax')) / 100;
        //     $total = $total + $xtotal;
        // }
        if ($fnd < 1) {
            return response()->json(0);
        } else {
            $coupon = Coupon::where('code', '=', $code)->first();
            $curr = $this->curr;
            if ($coupon->times != null) {
                if ($coupon->times == "0") {
                    return response()->json(0);
                }
            }
            $today = date('Y-m-d');
            $from = date('Y-m-d', strtotime($coupon->start_date));
            $to = date('Y-m-d', strtotime($coupon->end_date));
            if ($from <= $today && $to >= $today) {
                if ($coupon->status == 1) {
                   
                    $oldCart = Session::has('cart') ? Session::get('cart') : null;
                    $val = Session::has('alreadyy') ? Session::get('alreadyy') : null;
                    
                    if ($val == $code) {
                        return response()->json([2, $val]);
                    }
                    $cart = Cart::restoreCart($oldCart);
                    if ($coupon->type == 0) {
                        if ($coupon->price >= $total) {
                            return response()->json(3);
                        }
                        Session::put('already', $code);
                        $coupon->price = (int)$coupon->price;

                        $oldCart = Session::get('cart');
                        // $cart = new Cart($oldCart);
                        $cart = Cart::restoreCart($oldCart);
                        $val = $total / 100;
                        $sub = $val * $coupon->price;
                        $total = $total - $sub;
                        $total = $total + (isset($_GET['shipping_cost']) ? (float)$_GET['shipping_cost'] : 0);
                        $data[0] = \PriceHelper::showCurrencyPrice($total);
                        $data[1] = $code;
                        $data[2] = round($sub, 2);
                        Session::put('coupon', $data[2]);
                        Session::put('coupon_code', $code);
                        Session::put('coupon_id', $coupon->id);
                        Session::put('coupon_total1', round($total, 2));
                        Session::forget('coupon_total');

                        $data[3] = $coupon->id;
                        $data[4] = $coupon->price . "%";
                        $data[5] = 1;
                        $data[6] = round($total, 2);
                        Session::put('coupon_percentage', $data[4]);
                        return response()->json($data);
                    } else {
                        if ($coupon->price >= $total) {
                            return response()->json(3);
                        }
                        Session::put('already', $code);
                        $total = $total - round($coupon->price * $curr->value, 2);
                        $data[0] = $total;
                        $data[1] = $code;
                        $data[2] = $coupon->price * $curr->value;
                        $data[3] = $coupon->id;
                        $data[4] = \PriceHelper::showCurrencyPrice($data[2]);
                        $data[0] = \PriceHelper::showCurrencyPrice($data[0]);
                        Session::put('coupon', $data[2]);
                        Session::put('coupon_code', $code);
                        Session::put('coupon_id', $coupon->id);
                        Session::put('coupon_total1', round($total, 2));
                        Session::forget('coupon_total');
                        $data[1] = $code;
                        $data[2] = round($coupon->price * $curr->value, 2);
                        $data[3] = $coupon->id;
                        $data[5] = 1;
                        $data[6] = round($total, 2);
                        Session::put('coupon_percentage', $data[4]);
                        return response()->json($data);
                    }
                } else {
                    // dd('coupon expired');
                    return response()->json(0);
                }
            } else {
                dd('coupon expired');
                return response()->json(0);
            }
        }
    }
    // public function couponcheck()
    // {
    //     $code = $_GET['code'];
    //     $user = auth()->user();
    //     $coupon = Coupon::where('code', '=', $code)->first();
    
    //     if (!$coupon) {
    //         return response()->json(0); // Coupon not found
    //     }
    
    //     // If coupon already used by user
    //     if ($user && Order::where('coupon_code', $code)->where('user_id', $user->id)->exists()) {
    //         return response()->json(8); // Already used
    //     }
    
    //     // Removing coupon logic
    //     if (isset($_GET['applied_coupon'])) {
    //         Session::forget('already');
    //         Session::forget('coupon');
    //         Session::forget('coupon_code');
    //         Session::forget('coupon_id');
    //         Session::forget('coupon_total1');
    //         Session::forget('coupon_percentage');
    //         $total = preg_replace('/[^0-9.]/', '', $_GET['total']) + (float)($_GET['coupon_discount']);
    //         return response()->json(['total' => "₹" . $total, 'remove_coupen' => true]);
    //     }
    
    //     $cart = Session::get('cart');
    //     if (!$cart || !isset($cart->items)) {
    //         return response()->json(0); // No cart
    //     }
    
    //     // Check applicable items
    //     $discount_items = [];
    //     foreach ($cart->items as $key => $item) {
    //         $product = Product::find($item['item']['id']);
    //         if (!$product) continue;
    
    //         if (
    //             ($coupon->coupon_type == 'category' && $product->category_id == $coupon->category) ||
    //             ($coupon->coupon_type == 'sub_category' && $product->sub_category == $coupon->sub_category) ||
    //             ($coupon->coupon_type == 'child_category' && $product->child_category == $coupon->child_category)
    //         ) {
    //             $discount_items[] = $key;
    //         }
    //     }
    
    //     if (empty($discount_items)) {
    //         return response()->json(0); // No applicable items for coupon
    //     }
    
    //     // Total discountable amount
    //     $main_discount_price = 0;
    //     foreach ($cart->items as $ckey => $cproduct) {
    //         if (in_array($ckey, $discount_items)) {
    //             $main_discount_price += $cproduct['price'];
    //         }
    //     }
    
    //     $total = (float)preg_replace('/[^0-9\.]/ui', '', $main_discount_price);
    
    //     // Check validity
    //     if ($coupon->times !== null && $coupon->times == "0") {
    //         return response()->json(0);
    //     }
    
    //     $today = date('Y-m-d');
    //     $from = date('Y-m-d', strtotime($coupon->start_date));
    //     $to = date('Y-m-d', strtotime($coupon->end_date));
    
    //     if ($today < $from || $today > $to || $coupon->status != 1) {
    //         return response()->json(0); // Expired or inactive
    //     }
    
    //     // Prevent duplicate apply in session
    //     if (Session::get('already') === $code) {
    //         return response()->json([2, $code]); // Already applied in session
    //     }
    
    //     Session::put('already', $code);
    //     $curr = $this->curr;
    
    //     $shippingCost = isset($_GET['shipping_cost']) ? (float)$_GET['shipping_cost'] : 0;
    
    //     // Calculate discount
    //     if ($coupon->type == 0) {
    //         // Percentage
    //         if ($coupon->price >= 100) return response()->json(3); // Invalid %
    
    //         $discountAmount = round(($total * $coupon->price) / 100, 2);
    //         if ($discountAmount >= $total) return response()->json(3);
    
    //         $finalTotal = $total - $discountAmount + $shippingCost;
    
    //         $data = [
    //             \PriceHelper::showCurrencyPrice($finalTotal),
    //             $code,
    //             $discountAmount,
    //             $coupon->id,
    //             $coupon->price . "%",
    //             1,
    //             round($finalTotal, 2)
    //         ];
    //     } else {
    //         // Fixed
    //         $discountAmount = round($coupon->price * $curr->value, 2);
    //         if ($discountAmount >= $total) return response()->json(3);
    
    //         $finalTotal = $total - $discountAmount + $shippingCost;
    
    //         $data = [
    //             \PriceHelper::showCurrencyPrice($finalTotal),
    //             $code,
    //             $discountAmount,
    //             $coupon->id,
    //             \PriceHelper::showCurrencyPrice($discountAmount),
    //             1,
    //             round($finalTotal, 2)
    //         ];
    //     }
    
    //     // Set session values
    //     Session::put('coupon', $discountAmount);
    //     Session::put('coupon_code', $code);
    //     Session::put('coupon_id', $coupon->id);
    //     Session::put('coupon_total1', round($finalTotal, 2));
    //     Session::put('coupon_percentage', $data[4]);
    //     Session::forget('coupon_total');
    
    //     return response()->json($data);
    // }
    


    public function applyCoupon(Request $request)
    {
        $couponCode = $request->input('coupon_code');
        // dd($request->total);
        // Validate the coupon code
        $coupon = Coupon::where('code', $couponCode)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code.'
            ]);
        }

        // Assuming you have a cart and a method to calculate the discounted price
        // $cart = $this->getCart(); // Replace this with your cart retrieval logic
        // $cart = Session::get('cart');

        // dd($cart);
        // Apply discount to the total price
        // $discountedPrice = $request->total - $coupon->discount;  // Assuming 'discount' is a field in your Coupon model
        $val = $request->total;
        $total = (float)preg_replace('/[^0-9\.]/ui', '', $val);

        $val = $total / 100;
        $sub = $val * $coupon->price;
        $total = $total - $sub;
        $ftotal = round($total, 1);

        $discountedPrice = \PriceHelper::showCurrencyPrice($ftotal);

        // $discountedPrice = round($discountedPrice, 2);
        // dd($discountedPrice);
        // Optionally save coupon details in session for later use
        // session(['coupon' => $coupon]);

        return response()->json([
            'success' => true,
            'new_total' => $discountedPrice,
            'discount' => $sub,

        ]);
    }
}
