<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Http;
use App\Models\Student;
use DB;

class authorizationController extends Controller
{
    public function personalAccessLogin (Request $request){

        $login = $request->validate([
            'email' => 'required|string|exists:students,email,status,1',
            'password' => 'required|string'
        ]);
        if(!Auth::attempt($login)){
            return response(["message" => "Invalid login credentials"], 401);
        }

        $user = Auth::user();
        $accessToken = Auth::user()->createToken('authToken')->accessToken;
        
        $now = \DateTime::createFromFormat('Y-m-d H:i:s', now());
        
        $student = Student::find($user->id);
        $student->update(
                        [ 'last_login' => $now, 'updated_at' => now()]
                        );
        // dd($request->all(), $accessToken, $student);
        return response()->json(["student" => $user, "access_token" => $accessToken], 200);
        
    }

    public function adminAccessLogin (Request $request){
        
        $login = $request->validate([
            'email' => 'required|string|exists:students,email,status,1',
            // 'password' => 'required|string'
        ]);

        $user = Student::where('email', $request->email)->first();
        // $accessToken = Auth::user()->createToken('authToken')->accessToken;
        $accessToken = $user->createToken('access_token')->accessToken;
        // dd($accessToken, $user);

        return response()->json(["student" => $user, "access_token" => $accessToken], 200);

    }

    public function verifyToken(Request $request){
        if (Auth::guard('api')->check()) {
            return response(["message" => "Valid login token"], 200);
        } else {
            return response(["message" => "Invalid login token"], 401);
        }
    }
}
