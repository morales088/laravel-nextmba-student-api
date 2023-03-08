<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Partnership;
use Illuminate\Http\Request;
use App\Models\PartnershipInvite;
use App\Models\WithdrawalPayment;
use Illuminate\Support\Facades\DB;
use App\Models\PartnershipWithdraw;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $cache_key = 'student_'.$userId;
        $cacheDuration = 60;
        
        $student = Cache::remember($cache_key, $cacheDuration, function () use($userId) {
            return Student::find($userId);
        });
        
        // sleep(1); // slowdown the request for set seconds

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

            if (Cache::has('partnership_'.$userId)) {
                $partnership = Cache::get('partnership'.$userId);
            } else {
                $partnership = $student->partnership;
                Cache::put('partnership'.$userId, $partnership, 60);
            }

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
            $beginnerCommissionPercent = env('beginnerCommissionPercent');
            
            $application = Partnership::create([
                'student_id' => $userId,
                'affiliate_code' => $affiliate_code,
                'affiliate_status' => 1, // approved
                'percentage' => $beginnerCommissionPercent
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
        $partnership = Partnership::where('student_id', $userId)->first();

        if (!$partnership) {
            return response()->json([
                'error' => 'No affiliate partnership found for the student.'
            ], 404);
        }

        $currentPage = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        $offset = $request->query('offset', ($currentPage - 1) * $perPage);
        
        $affiliatePayments = Payment::where('from_student_id', $partnership->student_id)
            ->select('commission_status', 'price', 'email', 'created_at', 'commission_percentage')
            ->offset($offset)->limit($perPage)
            ->orderBy('created_at', 'DESC')
            ->get();

        $paymentItems = Payment::where('from_student_id', $partnership->student_id)->count();
        $partnership_payments = [];

        foreach ($affiliatePayments as $payment) {
            $partnership_payments[] = [
                'price' => $payment->price,
                'commission_percentage' => $payment->commission_percentage,
                'commission_amount' => round($payment->price * $payment->commission_percentage, 2),
                'commission_status' => $payment->commission_status,
                'email' => $payment->email,
                'created_at' => $payment->created_at->toDateTimeString()
            ];
        }

        $partnershipPayments = new LengthAwarePaginator($partnership_payments, $paymentItems, $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);

        return response()->json([
            'affiliatePayments' => $partnershipPayments,
            'commission_percentage' => $partnership->percentage,
        ]);
    }

    public function getWithdraws(Request $request) {

        $currentPage = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        $offset = $request->query('offset', ($currentPage - 1) * $perPage);

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:students,id'
        ]);

        $withdrawals = Auth::user()->partnership_withraws()
                        ->with('user')
                        ->offset($offset)->limit($perPage)
                        ->orderBy('created_at', 'DESC')
                        ->get();
        
        $withdrawItems = Auth::user()->partnership_withraws()->count();

        $affiliateWithdraws = new LengthAwarePaginator($withdrawals, $withdrawItems, $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);
                            
        if($affiliateWithdraws->count()>0){
            return response()->json([
                'message' => "Student withdrawals retrieved successfully.",
                'withdrawals' => $affiliateWithdraws
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

        // $unpaid_commission = DB::TABLE('payments as p')
        //                     ->leftJoin('withdrawal_payments as wp', 'wp.payment_id', '=', 'p.id')
        //                     ->leftJoin('partnership_withdraws as pw', function($join)
        //                         {
        //                             $join->on('wp.withdrawal_id', '=', 'pw.id');
        //                             $join->on('p.from_student_id', '=' ,'pw.student_id');
        //                             // $join->on('pw.commission_status', '=', DB::raw(2));
        //                         })
        //                     ->where('p.from_student_id', $userId)
        //                     ->where('p.status', 'paid')
        //                     ->where('p.commission_status', 0)
        //                     // ->whereNull('pw.id')
        //                     ->select('p.*', 'pw.id as CS', DB::raw('(p.price * p.commission_percentage) as commission'))
        //                     ->get();

        $unpaid_commission = Payment::where('from_student_id', $userId)
                                ->where('status', 'paid')
                                ->where('commission_status', 0)
                                ->select('*', DB::raw('(price * commission_percentage) as commission'))
                                ->get();
                            
        $balance = $unpaid_commission->sum('commission');

        // dd($unpaid_commission->sum('commission') - $paid_commision->sum('commission'));
        // dd($balance, $unpaid_commission->toArray());
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
}