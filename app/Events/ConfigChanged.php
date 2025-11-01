<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConfigChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct()
    {
    }

    // 广播到的频道
    public function broadcastOn()
    {
        return new Channel('jh-shop');
    }

    // 广播事件名称
    public function broadcastAs()
    {
        return 'ConfigChanged';
    }

    // 广播的数据内容
    public function broadcastWith()
    {
        return [
        ];
    }
}
