<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use App\Models\AffiliateWithdraw;
use App\Models\WithdrawalPayment;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class AffiliateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function affiliateApplication(Request $request) {

        $userId = auth('api')->user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'numeric|min:1|exists:students,id',
        ]);

        $student = Student::find($userId);

        if ($student->affiliate_access === 0) {

            $existingAffiliate = Affiliate::where('student_id', $userId)->first();

            if (!$existingAffiliate) {

                return response()->json([
                    'message' => "Affiliate not found.",
                    'affiliate' => null
                ], 200);

            } elseif ($existingAffiliate->affiliate_status == 0) {

                return response()->json([
                    'message' => "Your affiliate application is still pending.",
                    'affiliate' => $student->affiliate
                ], 200);

            } elseif ($existingAffiliate->affiliate_status == 2) {

                return response()->json([
                    'message' => "Your affiliate application has been declined.",
                    'affiliate' => $student->affiliate
                ], 200);

            } else {

                return response()->json([
                    'message' => "Student already has a approved affiliate.",
                ], 200);
            }
            
        } else {

            return response()->json([
                'message' => "Student affiliate retrieved successfully.",
                'affiliate' => $student->affiliate
            ], 200);
        }
    }

    public function applyAffiliate(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:students,id'
        ]);
        
        $student = Student::find($userId);
        $existingAffiliate = Affiliate::where('student_id', $userId)->first();
        
        if (!$existingAffiliate) {

            $affiliate_code = bin2hex(random_bytes(5)); // generating temporary unique code
            $affiliateCommissionPercent = env('affiliateCommissionPercent');
            
            $application = Affiliate::create([
                'student_id' => $userId,
                'affiliate_code' => $affiliate_code,
                'affiliate_status' => 1, // approved
                'percentage' => $affiliateCommissionPercent
            ]);

            $student->update([
                'affiliate_access' => 1 // update to affiliate
            ]);

            return response()->json([
                'message' => "Affiliate application submitted successfully.",
                'application' => $application
            ], 201);
        }

        return response()->json([
            'message' => "You have already applied for affiliate."
        ], 201);
    }

    public function updateAffiliateCode(Request $request) {

        $userId = Auth::user()->id;
        $affiliate = Affiliate::where('student_id', $userId)
            ->whereIn('affiliate_status', [1])
            ->where('status', '<>', 0)
            ->first();

        $request->validate([
            'affiliate_code' => 'unique:affiliates,affiliate_code,'. $affiliate->id
        ]);

        if ($affiliate) {
             if ($request->affiliate_code == null) {
                $affiliate_code = $affiliate->affiliate_code;
            } else {
                $affiliate_code = $request->affiliate_code;
            }

            $affiliate->update([
                'affiliate_code' => $affiliate_code
            ]);

             return response()->json([
                'message' => "Affiliate code has been updated successfully.",
                'affiliate' => $affiliate
            ]);
        } else {
            return response()->json([
                'message' => "Affiliate not found.",
            ]);
        }
    }

    public function withdrawMethod(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);

        $request->validate([
            'id' => 'required|exists:affiliates,student_id',
            'withdraw_method' => 'sometimes|max:255'
        ]);

        $affiliate = Affiliate::where('student_id', $userId)
            ->whereIn('affiliate_status', [1])
            ->where('status', '<>', 0)
            ->first();
        
        if ($affiliate) {

            if ($request->withdraw_method == null) {
                $withdraw_method = $affiliate->withdraw_method;
            } else {
                $withdraw_method = $request->withdraw_method;
            }

            $affiliate->update([
                'withdraw_method' => $withdraw_method
            ]);

             return response()->json([
                'message' => "Withdraw method added successfully.",
                'withdrawMethod' => $affiliate
            ]);

        } else {
            return response()->json([
                'message' => "Affiliate not found.",
            ]);
        }
    }

    public function getAffiliatePayments(Request $request) {

        $userId = Auth::user()->id;
        $affiliate = Affiliate::where('student_id', $userId)->first();

        if (!$affiliate) {
            return response()->json([
                'error' => 'Affiliate not found for the student.'
            ], 404);
        }

        $currentPage = $request->query('page', 1);
        $perPage = $request->query('per_page', 10);
        $offset = $request->query('offset', ($currentPage - 1) * $perPage);
        
        $affiliatePayments = Payment::where('from_student_id', $affiliate->student_id)
            ->select('commission_status', 'price', 'email', 'created_at', 'commission_percentage')
            ->offset($offset)->limit($perPage)
            ->orderBy('created_at', 'DESC')
            ->get();

        $paymentItems = Payment::where('from_student_id', $affiliate->student_id)->count();
        $affiliate_payments = [];

        foreach ($affiliatePayments as $payment) {
            $affiliate_payments[] = [
                'price' => $payment->price,
                'commission_percentage' => $payment->commission_percentage,
                'commission_amount' => round($payment->price * $payment->commission_percentage, 2),
                'commission_status' => $payment->commission_status,
                'email' => $payment->email,
                'created_at' => $payment->created_at->toDateTimeString()
            ];
        }

        $affiliatePayments = new LengthAwarePaginator($affiliate_payments, $paymentItems, $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);

        return response()->json([
            'affiliatePayments' => $affiliatePayments,
            'commission_percentage' => $affiliate->percentage,
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

        $withdrawals = Auth::user()->affiliate_withdraws()
            ->with('user')
            ->offset($offset)->limit($perPage)
            ->orderBy('created_at', 'DESC')
            ->get();

        $withdrawItems = Auth::user()->affiliate_withdraws()->count();

        $affiliateWithdraws = new LengthAwarePaginator($withdrawals, $withdrawItems, $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query()
        ]);
                            
        if ($affiliateWithdraws->count() > 0) {

            return response()->json([
                'message' => "Student withdrawals retrieved successfully.",
                'withdrawals' => $affiliateWithdraws
            ], 200);

        } else {
            return response()->json([
                'message' => "Withdrawals not found for this student."
            ], 204);
        }
    }


    public function getWithdrawalsInfo(Request $request) {

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);

        $request->validate([
            'id' => 'required|exists:students,id'
        ]);
        
        $total_commission = Auth::user()->commission()
            ->where('status', 'paid')
            ->sum(DB::raw('(price * commission_percentage)'));

        $paid_commission = Auth::user()->affiliate_withdraws()
            ->where('commission_status', 2)
            ->sum('withdraw_amount');

        $current_balance = $total_commission - $paid_commission;
        
        return response()->json([
            'message' => "Withdrawals info retrieved successfully.",
            'total_commission' => $total_commission,
            'paid_commission' => $paid_commission,
            'current_balance' => $current_balance,
        ], 200);
    }

    public function requestWithdrawal(Request $request) {

        $userId = Auth::user()->id;

        $pending = AffiliateWithdraw::where('student_id', $userId)
            ->where('commission_status', 1)
            ->get();
                        
        if (!$pending->isEmpty()) {
            return response()->json([
                'message' => "Already has pending request.",
            ], 400);
        }
        
        $unpaid_commission = Payment::where('from_student_id', $userId)
            ->where('status', 'paid')
            ->where('commission_status', 0)
            ->select('*', DB::raw('(price * commission_percentage) as commission'))
            ->get();
                            
        $balance = $unpaid_commission->sum('commission');

        if ($balance <= 0) {
            return response()->json([
                'message' => "You have zero (0) balance.",
            ], 405);
        }

        $withdraw = DB::transaction(function() use ($request, $userId, $balance, $unpaid_commission) {

            $newWithdraw = new AffiliateWithdraw;
            $newWithdraw->student_id = $userId;
            $newWithdraw->withdraw_amount = $balance;
            $newWithdraw->save();
            
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