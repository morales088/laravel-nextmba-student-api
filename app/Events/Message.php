<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Message implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        public string $name,
        public string $message,
        public string $channel,
        public string $message_id,
        public string $date_sent,
        public string $chat_moderator,
        public string $pro_access,
        
    )
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // dd($this->name, $this->message, $this->channel, 'presence-'.$this->channel);
        return new PrivateChannel($this->channel);
        // return new PresenceChannel($this->channel);
        // return [$this->channel];
    }

    public function broadcastAs()
    {
        return 'chat_message';
    }
}
