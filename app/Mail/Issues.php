<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Issues extends Mailable
{
    use Queueable, SerializesModels;
    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('NEXT MBA Account concerns')->replyTo($this->data['email'], $this->data['name'])->view('email.issues')->with([
            'email' => $this->data['email'],
            'name' => $this->data['name'],
            'messages' => $this->data['messages'],
        ]);
    }
}
