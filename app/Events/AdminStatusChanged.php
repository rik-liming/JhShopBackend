<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

	public $admin_id;

    public function __construct($admin_id)
    {
        $this->admin_id = $admin_id;
    }

    // 广播到的频道
    public function broadcastOn()
    {
        return new Channel('jh-admin');
    }

    // 广播事件名称
    public function broadcastAs()
    {
        return 'AdminStatusChanged';
    }

    // 广播的数据内容
    public function broadcastWith()
    {
        return [
            'admin_id' => $this->admin_id,
        ];
    }
}
