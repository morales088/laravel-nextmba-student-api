<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\Message;
use DB;

class ChatController extends Controller
{
    public function send(Request $request){

        $request->validate([
            'name' => 'required|string',
            'message' => 'required|string',
            'channel' => 'required|string',
        ]);

        // dd($request->all(), $request->name, $request->message);

        // event(new Message($request->name, $request->message, $request->channel) );
        broadcast(new Message($request->name, $request->message, $request->channel) )->toOthers();

        return response(["info" => $request->all()], 200);
    }

    // public function subscribe(Request $request){

    //     $request->validate([
    //         'channel' => 'required|string',
    //     ]);

    //     $channel = Echo.channel($request->channel);
    //     dd($channel);
    // }
}
