<?php

namespace App\Http\Controllers\api;

use App\Models\User;
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
                ], 400);
            }
        } else {
            return response()->json([
                'message' => "Student partnership retrieved successfully.",
                'partnership' => $student->partnership
            ], 400);
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
        $request->validate([
            'affiliate_code' => 'string|required|unique:partnerships,affiliate_code'
        ]);

        $partnership = Partnership::where('student_id', $userId)
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

        $userId = Auth::user()->id;
        $request->query->add(['id' => $userId]);
        $request->validate([
            'id' => 'required|exists:students,id',
            'invitation_code' => 'required|exists:partnerships,affiliate_code'
        ]);

        $partnership = Partnership::where('affiliate_code', $request->invitation_code)
                        ->whereIn('affiliate_status', [1])
                        ->where('status', '<>', 0)
                        ->first();

        if ($partnership) {
            // check if the student uses own affiliate code
            if ($request->id == $partnership->student_id) {
                return response()->json([
                    'message' => "You cannot use your own affiliate code."
                ], 400);
            }

            $partnership_invite = PartnershipInvite::create([
                'student_id' => $request->id,
                'invitation_code' => $request->invitation_code,
                'from_student_id' => $partnership->student_id
            ]);
            return response()->json([
                'message' => "Invitation code used successfully.",
                'partnershipInvite' => $partnership
            ], 201);

        } else {
            return response()->json([
                'message' => "Invalid invitation code / doesn't exist."
            ], 400);
        }
    }
}