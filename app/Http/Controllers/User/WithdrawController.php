<?php

namespace App\Http\Controllers\User;

use App\{
    Models\User,
    Models\Withdraw,
    Models\Currency,
    Models\Product
};

use Illuminate\Http\Request;

class WithdrawController extends UserBaseController
{

  	public function index()
    {
        $withdraws = Withdraw::where('user_id','=',$this->user->id)->where('type','=','user')->latest('id')->get();
        $sign = Currency::where('is_default','=',1)->first();        
        return view('user.withdraw.index',compact('withdraws','sign'));
    }

    public function create()
    {
        $sign = Currency::where('is_default','=',1)->first();
        return view('user.withdraw.withdraw' ,compact('sign'));
    }


    public function store(Request $request)
    {
        $from = User::findOrFail($this->user->id);
        
        $withdrawcharge = $this->gs;
        $charge = $withdrawcharge->withdraw_fee;

        if($request->amount > 0){
            $amount = $request->amount;
            $total_referral_amount = $from->current_balance;

            if ($total_referral_amount >= $amount){
                $fee = (($withdrawcharge->withdraw_charge / 100) * $amount) + $charge;
                $finalamount = $amount - $fee;
                if ($total_referral_amount >= $finalamount){
                $finalamount = number_format((float)$finalamount,2,'.','');

                if ($from->current_balance >= $finalamount) {
                    $from->current_balance -= $finalamount;
                } else {
                    $remaining_amount = $finalamount - $from->current_balance;
                    $from->current_balance = 0;
                    $from->current_balance -= $remaining_amount;
                }


                
                $from->update();
                $newwithdraw = new Withdraw();
                $newwithdraw['user_id'] = $this->user->id;
                $newwithdraw['method'] = $request->methods;
                $newwithdraw['acc_email'] = $request->acc_email;
                $newwithdraw['upi_id'] = $request->upi_id ?? null;
                $newwithdraw['iban'] = $request->iban;
                $newwithdraw['country'] = $request->acc_country;
                $newwithdraw['acc_name'] = $request->acc_name;
                $newwithdraw['address'] = $request->address;
                $newwithdraw['swift'] = $request->swift;
                $newwithdraw['reference'] = $request->reference;
                $newwithdraw['amount'] = $finalamount;
                $newwithdraw['fee'] = $fee;
                $newwithdraw['type'] = 'user';
                $newwithdraw->save();

                return response()->json(__('Withdraw Request Sent Successfully.')); 
            }else{
                return response()->json(array('errors' => [ 0 => __('Insufficient Balance.') ])); 

            }
            }else{
                return response()->json(array('errors' => [ 0 => __('Insufficient Balance.') ])); 

            }
        }
            return response()->json(array('errors' => [ 0 => __('Please enter a valid amount.') ])); 

    }

    public function store1(Request $request)
    {
        $from = User::findOrFail($this->user->id);
        // dd( $from );

        $withdrawcharge = $this->gs;
        $charge = $withdrawcharge->withdraw_fee;

        if($request->amount > 0){
            $amount = $request->amount;
            $total_referral_amount = $from->affilate_income + $from->referral_income;
            if ($total_referral_amount >= $amount){
                $fee = (($withdrawcharge->withdraw_charge / 100) * $amount) + $charge;
                $finalamount = $amount - $fee;
                if ($total_referral_amount >= $finalamount){
                $finalamount = number_format((float)$finalamount,2,'.','');

                if ($from->affilate_income >= $finalamount) {
                    $from->affilate_income -= $finalamount;
                } else {
                    $remaining_amount = $finalamount - $from->affilate_income;
                    $from->affilate_income = 0;
                    $from->referral_income -= $remaining_amount;
                }


                
                $from->update();
                $newwithdraw = new Withdraw();
                $newwithdraw['user_id'] = $this->user->id;
                $newwithdraw['method'] = $request->methods;
                $newwithdraw['acc_email'] = $request->acc_email;
                $newwithdraw['upi_id'] = $request->upi_id ?? null;
                $newwithdraw['iban'] = $request->iban;
                $newwithdraw['country'] = $request->acc_country;
                $newwithdraw['acc_name'] = $request->acc_name;
                $newwithdraw['address'] = $request->address;
                $newwithdraw['swift'] = $request->swift;
                $newwithdraw['reference'] = $request->reference;
                $newwithdraw['amount'] = $finalamount;
                $newwithdraw['fee'] = $fee;
                $newwithdraw['type'] = 'user';
                $newwithdraw->save();

                return response()->json(__('Withdraw Request Sent Successfully.')); 
            }else{
                return response()->json(array('errors' => [ 0 => __('Insufficient Balance.') ])); 

            }
            }else{
                return response()->json(array('errors' => [ 0 => __('Insufficient Balance.') ])); 

            }
        }
            return response()->json(array('errors' => [ 0 => __('Please enter a valid amount.') ])); 

    }
}