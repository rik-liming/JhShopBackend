<?php

namespace App\Events;

use Pusher\Pusher;

public function __construct()
{
    $this->pusher = new Pusher(
        env('PUSHER_APP_KEY'),
        env('PUSHER_APP_SECRET'),
        env('PUSHER_APP_ID'),
        ['cluster' => env('PUSHER_APP_CLUSTER')]
    );
}

public function broadcastMessage($message)
{
    $this->pusher->trigger('channel', 'new_message', ['message' => $message]);
}
