<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Partnership;
use Illuminate\Http\Request;
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
}
