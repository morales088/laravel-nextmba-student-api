<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Partnership;
use Illuminate\Http\Request;
use App\Models\PartnershipInvite;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\PartnershipWithdraw;
use Illuminate\Support\Facades\Auth;
use App\Models\WithdrawalPayment;

class PartnershipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function partnershipApplication(Request $request) {

        $userId = auth('api')->user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'numeric|min:1|exists:students,id',
        ]);

        $student = Student::find($userId);
        if ($student->affiliate_access === 0) {
            $existingPartnership = Partnership::where('student_id', $userId)->first();
            if (!$existingPartnership) {
                return response()->json([
                    'message' => "No partnership found.",
                    'application' => null
                ], 200);
            } elseif ($existingPartnership->affiliate_status == 0) {
                return response()->json([
                    'message' => "Your partnership application is still pending.",
                    'application' => $student->partnership
                ], 200);
            } elseif ($existingPartnership->affiliate_status == 2) {
                return response()->json([
                    'message' => "Your partnership application has been declined.",
                    'application' => $student->partnership
                ], 200);
            } else {
                return response()->json([
                    'message' => "Student already has a approved partnership.",
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Student partnership retrieved successfully.",
                'partnership' => $student->partnership
            ], 200);
        }
    }

    public function applyPartnership(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:students,id'
        ]);
        
        $student = Student::find($userId);
        $existingPartnership = Partnership::where('student_id', $userId)->first();
        
        if (!$existingPartnership) {
            $affiliate_code = bin2hex(random_bytes(5)); // generating temporary unique code
            $baseCommission = env('baseCommissionPercent');
            
            $application = Partnership::create([
                'student_id' => $userId,
                'affiliate_code' => $affiliate_code,
                'affiliate_status' => 1, // approved
                'percentage' => $baseCommission
            ]);

            $student->update([
                'affiliate_access' => 1 // update to partner
            ]);

            return response()->json([
                'message' => "Partnership application submitted successfully.",
                'application' => $application
            ], 201);
        }

        return response()->json([
            'message' => "You have already applied for partnership."
        ], 201);
    }

    public function updateAffiliateCode(Request $request) {

        $userId = Auth::user()->id;
        $partnership = Partnership::where('student_id', $userId)
                        ->whereIn('affiliate_status', [1])
                        ->where('status', '<>', 0)
                        ->first();

        $request->validate([
            'affiliate_code' => 'unique:partnerships,affiliate_code,'. $partnership->id
        ]);

        if ($partnership) {
             if ($request->affiliate_code == null) {
                $affiliate_code = $partnership->affiliate_code;
            } else {
                $affiliate_code = $request->affiliate_code;
            }

            $partnership->update([
                'affiliate_code' => $affiliate_code
            ]);

             return response()->json([
                'message' => "Affiliate code has been updated successfully.",
                'partnership' => $partnership
            ]);
        } else {
            return response()->json([
                'message' => "No partnership found.",
            ]);
        }
    }

    public function withdrawMethod(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:partnerships,student_id',
            'withdraw_method' => 'sometimes|max:255'
        ]);

        $partnership = Partnership::where('student_id', $userId)
                        ->whereIn('affiliate_status', [1])
                        ->where('status', '<>', 0)
                        ->first();
        
        if ($partnership) {
            if ($request->withdraw_method == null) {
                $withdraw_method = $partnership->withdraw_method;
            } else {
                $withdraw_method = $request->withdraw_method;
            }

            $partnership->update([
                'withdraw_method' => $withdraw_method
            ]);
             return response()->json([
                'message' => "Withdraw method added successfully.",
                'withdrawMethod' => $partnership
            ]);
        } else {
            return response()->json([
                'message' => "No partnership found.",
            ]);
        }
    }

    public function getAffiliatePayments(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:partnerships,student_id'
        ]);

        $student = Student::findOrFail($request->id);
        $partnership = Partnership::where('student_id', $userId)->first();
        if (!$partnership) {
            return response()->json([
                'error' => 'No affiliate partnership found for the student.'
            ], 404);
        }
        
        $affiliatePayments = Payment::where('from_student_id', $partnership->student_id)
            // ->where('affiliate_code', $partnership->affiliate_code)
            ->select('commission_status', 'price', 'email', 'created_at', 'commission_percentage')
            ->get();

        $commission_amount = 0;
        $partnership_payments = [];

        foreach ($affiliatePayments as $payment) {
            $partnership_payments[] = [
                // $commission_amount += $payment->price * $payment->commission_percentage,
                'price' => $payment->price,
                'commission_percentage' => $payment->commission_percentage,
                'commission_amount' => round($payment->price * $payment->commission_percentage, 2),
                'commission_status' => $payment->commission_status,
                'email' => $payment->email,
                'created_at' => $payment->created_at->toDateTimeString()
            ];
        }
        return response()->json([
            'affiliatePayments' => $partnership_payments,
            'commission_percentage' => $partnership->percentage, 
        ]);
    }

//     public function useAffiliateCode(Request $request) {

//         $userId = Auth::user()->id;
//         $request->query->add(['id' => $userId]);
//         $request->validate([
//             'id' => 'required|exists:students,id',
//             'invitation_code' => 'required|exists:partnerships,affiliate_code'
//         ]);

//         $partnership = Partnership::where('affiliate_code', $request->invitation_code)
//                         ->whereIn('affiliate_status', [1])
//                         ->where('status', '<>', 0)
//                         ->first();

//         if ($partnership) {
//             // check if the student uses own affiliate code
//             if ($request->id == $partnership->student_id) {
//                 return response()->json([
//                     'message' => "You cannot use your own affiliate code."
//                 ], 400);
//             }
            

//             $student_payments = Payment::where('student_id', $request->id)->get();
//                                 // ->where('invitation_code', $request->invitation_code)
//                                 // ->get();
//             // dd($student_payments);

//             $commission_amount = 0;
//             $commission_percent = $partnership->percentage;
//             foreach ($student_payments as $payment) {
//                 $commission_amount += $payment->price * $commission_percent;
//             }

//             $partnership_invite = PartnershipInvite::create([
//                 'student_id' => $request->id,
//                 'payment_id' => $payment->id,
//                 'invitation_code' => $request->invitation_code,
//                 'from_student_id' => $partnership->student_id,
//                 'commission_amount' => $commission_amount,
//                 'commission_percent' => $commission_percent,
//             ]);

//             // $partnership_invite = PartnershipInvite::create([
//             //     'student_id' => $request->id,
//             //     'payment_id' => $payment->id,
//             //     'invitation_code' => $request->invitation_code,
//             //     'from_student_id' => $partnership->student_id,
//             // ]);

//             return response()->json([
//                 'partnershipInvite' => $partnership_invite
//             ], 201);

//         } else {
//             return response()->json([
//                 'message' => "Invalid invitation code / doesn't exist."
//             ], 400);
//         }
//     }


//     public function requestWithdrawal(Request $request) {

//         $request->validate([
//             'student_id' => 'required|integer',
//         ]);

//         $paymentId = Payment::where('student_id', $request->student_id)->first()->id;
//         $existingPartnership = Partnership::where('student_id', $request->student_id)
//                                 ->whereIn('affiliate_status', [1])
//                                 ->first();
//         // dd($existingPartnership);
//         $commissionPercent = Partnership::where('student_id', $request->student_id)
//                                 ->first()->percentage;
//         // dd($commissionPercent);
                            
//         $totalPayments = Payment::where('student_id', $request->student_id)->sum('price');
//         // $totalPayments = Payment::where('student_id', $request->student_id)->get();
//         // dd($totalPayments);
//         $commissionAmount = $totalPayments * intval($existingPartnership->percentage);

//         $partnershipWithdrawal = PartnershipWithdraws::create([
//             'student_id' => $existingPartnership->student_id,
//             'payment_id' => $payment->id,
//             'commission_amount' => $commissionAmount,
//             'commission_status' => 0, //pending
//             'commission_percent' => $existingPartnership->percentage
//         ]);

//         return response()->json([
//             'message' => "Partnership withdrawal request submitted successfully.",
//             'partnershipWithdrawal' => $partnershipWithdrawal
//         ]);
//     }

//     public function getWithdrawals(Request $request) {

//         $userId = Auth::user()->id;
//         $request->query->add(['id' => $userId]);
//         $request->validate([
//             'id' => 'required|exists:students,id'
//         ]);
        
//         // $withdrawals = PartnershipWithdraws::where('student_id', $userId)->get();
//         // $withdrawals = Student::find($userId)->withdraws;
//         $withdrawals = Auth::user()->partnershipWithdraws()->with('payment')->get();
//         // dd($withdrawals);
 
//         if($withdrawals->count()>0){
//             return response()->json([
//                 'message' => "Student withdrawals retrieved successfully.",
//                 'withdrawals' => $withdrawals
//             ], 200);
//         } else {
//             return response()->json([
//                 'message' => "No withdrawals found for this student."
//             ], 404);
//         }
//     }


    public function getWithdraws(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:students,id'
        ]);
        
        // $withdrawals = Auth::user()->partnershipWithdraws()->with('payment')->get();
        $withdrawals = Auth::user()->partnership_withraws()
                            ->with('user')
                            // ->where('status', 1)
                            ->get();
                            
        // dd($withdrawals);
 
        if($withdrawals->count()>0){
            return response()->json([
                'message' => "Student withdrawals retrieved successfully.",
                'withdrawals' => $withdrawals
            ], 200);
        } else {
            return response()->json([
                'message' => "No withdrawals found for this student."
            ], 204);
        }
    }


    public function getWithdrawalsInfo(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:students,id'
        ]);
        
        $total_commision = Auth::user()->commision()
                        ->where('status', 'paid')
                        // ->select(DB::raw('(price * commission_percentage) as commission_amount'))
                        ->sum(DB::raw('(price * commission_percentage)'));
                        // ->get()
                        // ->toArray();
        $paid_commision = Auth::user()->partnership_withraws()
                        ->where('commission_status', 2)
                        ->sum('withdraw_amount');
                        // ->get()
                        // ->toArray();

        $current_balance = $total_commision - $paid_commision;
        
        // dd($total_commision, $paid_commision, $current_balance);
 
        return response()->json([
            'message' => "Withdrawals info retrieved successfully.",
            'total_commision' => $total_commision,
            'paid_commision' => $paid_commision,
            'current_balance' => $current_balance,
        ], 200);
    }

    public function requestWithdrawal(Request $request) {

        $userId = Auth::user()->id;

        $pending = PartnershipWithdraw::where('student_id', $userId)
                        ->where('commission_status', 1)
                        ->get();
                        
        // dd(!$pending->isEmpty(), $pending);

        if(!$pending->isEmpty()){
            return response()->json([
                'message' => "already has pending request.",
            ], 400);
        }
        
        // $paid_commision = DB::TABLE('partnership_withdraws as pw')
        //                     ->leftJoin('withdrawal_payments as wp', 'pw.id', '=', 'wp.withdrawal_id')
        //                     // ->leftJoin('payments as p', 'p.id', '=', 'wp.payment_id')
        //                     ->leftJoin('payments as p', function($join)
        //                         {
        //                             $join->on('p.id', '=', 'wp.payment_id');
        //                             $join->on('p.from_student_id', '=', 'pw.student_id');
        //                         })
        //                     ->where('pw.commission_status', 2)
        //                     ->where('pw.student_id', $userId)
        //                     ->select('wp.*', 'pw.id as pw_id', DB::raw('(p.price * p.commission_percentage) as commission'))
        //                     ->get();

        $unpaid_commission = DB::TABLE('payments as p')
                            ->leftJoin('withdrawal_payments as wp', 'wp.payment_id', '=', 'p.id')
                            ->leftJoin('partnership_withdraws as pw', function($join)
                                {
                                    $join->on('wp.withdrawal_id', '=', 'pw.id');
                                    $join->on('p.from_student_id', '=' ,'pw.student_id');
                                    $join->on('pw.commission_status', '=', DB::raw(2));
                                })
                            ->where('p.from_student_id', $userId)
                            ->where('p.status', 'paid')
                            ->whereNull('pw.id')
                            ->select('p.*', DB::raw('(p.price * p.commission_percentage) as commission'))
                            ->get();
                            
        $balance = $unpaid_commission->sum('commission');

        // dd($unpaid_commission->sum('commission') - $paid_commision->sum('commission'));
        // dd($balance, $unpaid_commission);
        if($balance <= 0){
            return response()->json([
                'message' => "You have zero (0) balance.",
            ], 405);
        }

        $withdraw = DB::transaction(function() use ($request, $userId, $balance, $unpaid_commission) {
            $newWithdraw = new PartnershipWithdraw;
            $newWithdraw->student_id = $userId;
            $newWithdraw->withdraw_amount = $balance;
            $newWithdraw->save();
            // dd($newWithdraw->id, $balance);
            
            foreach ($unpaid_commission as $key => $value) {
                $withdrawal_payment = new WithdrawalPayment;
                $withdrawal_payment->withdrawal_id = $newWithdraw->id;
                $withdrawal_payment->payment_id = $value->id;
                $withdrawal_payment->save();
            }
            return $newWithdraw;
        });
 
        return response()->json([
            'message' => "Withdrawal request sent successfully.",
        ], 200);
    }

    public function withdrawalMethod(Request $request) {
        
        $userId = Auth::user()->id;

        $request->validate([
            'details' => 'required|string'
        ]);
        
        $paymen_method = Partnership::where("student_id", $userId)->update(["withdraw_method" => $request->details]);
 
        return response()->json([
            'message' => "Withdrawal method update successfully.",
        ], 200);

    }
}