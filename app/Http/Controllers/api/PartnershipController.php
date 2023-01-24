<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Partnership;
use Illuminate\Http\Request;
use App\Models\PartnershipInvite;
use App\Http\Controllers\Controller;

class PartnershipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function applyPartnership(Request $request) {

        $request->validate([
            'student_id' => 'required|exists:students,id|unique:partnerships,student_id'
        ]);

        $application = Partnership::create([
            'student_id' => $request->student_id,
            'affiliate_status' => 0, // pending
        ]);

        return response()->json([
            'application' => $application
        ], 201);
    }

    public function updateAffiliateCode(Request $request, $student_id) {

        $request->validate([
            'affiliate_code' => 'string|required|unique:partnerships,affiliate_code'
        ]);

        $partnership = Partnership::where('student_id', $student_id)
                        ->whereIn('affiliate_status', [1])
                        ->where('status', '<>', 0)
                        ->first();
        
        $partnership->update([
            'affiliate_code' => $request->affiliate_code
        ]);
        
        return response()->json([
            'message' => 'Affiliate code has been updated successfully.',
            'partnership' => $partnership
        ], 200);
    }

    public function useAffiliateCode(Request $request) {

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'invitation_code' => 'required|exists:partnerships,affiliate_code'
        ]);

        $partnership = Partnership::where('affiliate_code', $request->invitation_code)
                        ->whereIn('affiliate_status', [1])
                        ->where('status', '<>', 0)
                        ->first();
        if ($partnership) {
            /* // check student if affiliate/partner
            $isAffiliate = Partnership::where('student_id', $request->student_id)
                            ->whereIn('affiliate_status', [1])
                            ->where('status', '<>', 0)
                            ->first();
            if ($isAffiliate) {
                return response()->json([
                    'message' => "You are already an affiliate/partner. You cannot use another affiliate code."], 400);
            }
            // check student if already used the current code
            $isCodeUsed = PartnershipInvite::where('student_id', $request->student_id)
                            ->where('invitation_code', $request->invitation_code)
                            ->first();
            if ($isCodeUsed) {
                    return response()->json([
                        'message' => "You have already used this affiliate code."
                    ], 400);
            } */

            // check if the student uses own affiliate code
            if ($request->student_id == $partnership->student_id) {
                return response()->json([
                    'message' => "You cannot use your own affiliate code."
                ], 400);
            }

            $partnership_invite = PartnershipInvite::create([
                'student_id' => $request->student_id,
                'invitation_code' => $request->invitation_code,
                'from_student_id' => $partnership->student_id
            ]);
            return response()->json([
                'message' => "Invitation code used successfully.",
                'partnershipInvite' => $partnership
            ], 201);

        } else {
            return response()->json([
                'message' => "Invalid invitation code."
            ], 400);
        }
    }
}
