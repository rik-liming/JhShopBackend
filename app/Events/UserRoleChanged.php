<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRoleChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

	public $user_id;
	public $new_role;

    public function __construct($user_id, $new_role)
    {
        $this->user_id = $user_id;
        $this->new_role = $new_role;
    }

    // 广播到的频道
    public function broadcastOn()
    {
        return new Channel('jh-shop');
    }

    // 广播事件名称
    public function broadcastAs()
    {
        return 'UserRoleChanged';
    }

    // 广播的数据内容
    public function broadcastWith()
    {
        return [
            'user_id' => $this->user_id,
            'new_role' => $this->new_role,
        ];
    }
}
