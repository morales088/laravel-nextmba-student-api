<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Events\DeleteMessage;
use App\Events\Message;
use DB;

class ChatController extends Controller
{
    public function send(Request $request){

        $request->validate([
            'name' => 'required|string',
            'message' => 'required|string',
            'channel' => 'required|string',
            // 'is_delete' => 'boolean',
            // 'message_id' => 'string'
        ]);

        // dd($request->all(), !$request->is_delete);

        // if(!empty($request->is_delete) || $request->is_delete){

        //     broadcast(new DeleteMessage($request->message_id, $request->channel) )->toOthers();

        // }else{

            $now = now();
            $message_id = $now->format('YmdHisu');
            $request->query->add(['message_id' => $message_id]);

            broadcast( new Message($request->name, $request->message, $request->channel, $request->message_id, $now) )->toOthers();
        // }

        // event(new Message($request->name, $request->message, $request->channel) );
        
        $request->query->add(['date_sent' => $now]);

        return response(["info" => $request->all()], 200);
    }

    public function delete(Request $request){

        $request->validate([
            'channel' => 'required|string',
            'message_id' => 'required|string'
        ]);

        broadcast(new DeleteMessage($request->message_id, $request->channel) )->toOthers();

        return response(["info" => $request->all()], 200);
    }
    
}
