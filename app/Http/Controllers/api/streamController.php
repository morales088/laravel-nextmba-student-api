<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use DB;

class streamController extends Controller
{
    public function watchReplay(Request $request){
        $stream_link = env('STREAM_LINK');
        $stream_api_key = env('STREAM_API_TOKEN');
        $stream_account_id = env('STREAM_ACCCOUNT_ID');

        $stream = $request->validate([
            // 'module_id' => 'required|numeric|min:1|exists:modules,id',
            'uid' => 'required|string',
        ]);
        

        // $response_thumbnail = Http::acceptJson()->withHeaders([
        //     'Authorization' => "Bearer $stream_api_key",
        // ])->post($stream_link."/accounts/$stream_account_id/stream/$request->uid", [
        //     'thumbnailTimestampPct' => 0.1,
        // ]);

        $response = Http::acceptJson()->withHeaders([
            'Authorization' => "Bearer $stream_api_key",
        ])->get($stream_link."/accounts/$stream_account_id/stream/$request->uid", [
            
        ]);

        $cf_response_result = $response->json()['result'];
        // dd($cf_response_result['thumbnailTimestampPct'] == 0, $cf_response_result['thumbnailTimestampPct']);

        $time = now()->addHours(3);

        // $response_token = Http::acceptJson()->withHeaders([
        //     'Authorization' => "Bearer $stream_api_key",
        // ])->post($stream_link."/accounts/$stream_account_id/stream/$request->uid/token", [
        //     'exp' => strtotime($time),
        // ]);
        
        // $token_response = $response_token->json()['result'];
        // dd($token_response['token']);

        // return response()->json(["access_token" => $token_response['token'], "cloudflare_replay_result" => $cf_response_result], 200);
        return response()->json(["cloudflare_replay_result" => $cf_response_result], 200);


    }
}
