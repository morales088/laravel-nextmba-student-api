<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftEmail extends Mailable
{
    use Queueable, SerializesModels;
    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // dd($this->user);
        // if(isset($this->user['link'])){
        //     return $this->subject('NEXT University gift')->view('email.register-gift')->with([
        //         'email' => $this->user['email_sender'],
        //         'course' => $this->user['course'],
        //         'link' => $this->user['link'],
        //     ]);
        // }else{
        //     return $this->subject('NEXT University gift')->view('email.notify-gift')->with([
        //         'email' => $this->user['email_sender'],
        //         'course' => $this->user['course'],
        //     ]);
        // }

        return $this->subject('NEXT MBA gift')->view('email.notify-gift')->with([
            'email' => $this->user['email_sender'],
            'course' => $this->user['course'],
        ]);
        
    }
}
