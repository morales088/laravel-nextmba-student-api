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
            'email' => 'required|string',
            'password' => 'required|string'
        ]);
        if(!Auth::attempt($login)){
            return response(["message" => "Invalid login credentials"], 401);
        }


        $accessToken = Auth::user()->createToken('authToken')->accessToken;

        // dd($request->all(), $accessToken);
        return response()->json(["student" => Auth::user(), "access_token" => $accessToken], 200);
        
    }
}
