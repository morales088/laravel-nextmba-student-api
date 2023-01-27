<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Partnership;
use Illuminate\Http\Request;
use App\Models\PartnershipInvite;
use App\Http\Controllers\Controller;
use App\Models\PartnershipWithdraws;
use Illuminate\Support\Facades\Auth;

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
            $application = Partnership::create([
                'student_id' => $userId,
                'affiliate_status' => 0, // pending
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
            'affiliate_code' => 'string|required|unique:partnerships,affiliate_code,'. $partnership->id
        ]);

        $partnership->update([
            'affiliate_code' => $request->affiliate_code
        ]);
        
        return response()->json([
            'message' => 'Affiliate code has been updated successfully.',
            'partnership' => $partnership
        ], 200);
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

//     public function getAffiliatePayments(Request $request) {

//         $userId = Auth::user()->id;
//         $request->query->add(['id' => $userId]);
//         $request->validate([
//             'id' => 'required|exists:partnerships,student_id'
//         ]);

//         // $studentPayments = Payment::where('student_id', $request->student_id)->get();
//         $student = Student::findOrFail($request->id);
//         $studentPayments = $student->payments;
//         $partnership = $student->partnership;

//         $partnershipPercent = $partnership->percentage;
//         $commissionAmount = 0;
//         $paymentsWithCommission = [];

//         foreach($studentPayments as $payment) {
//             $commissionAmount = $payment->price * $partnershipPercent;
//             $paymentsWithCommission[] = [
//                 'payment_id' => $payment->id,
//                 'email' => $payment->email,
//                 'price' => $payment->price,
//                 'commission_amount' => $commissionAmount,
//                 'date' => $payment->created_at,
//             ];
//         }
//         return response()->json([
//             'payments' => $paymentsWithCommission,
//         ], 200);
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
}