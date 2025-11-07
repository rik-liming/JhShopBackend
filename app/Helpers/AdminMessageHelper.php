<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class AdminMessageHelper
{
    protected static function getListKey()
    {
        return "admin:messages";
    }

    protected static function getIndexKey()
    {
        return "admin:message_index";
    }

    protected static function getUnreadKey()
    {
        return "admin:unread_count";
    }

    /**
     * 添加消息
     */
    public static function pushMessage(array $message)
    {
        $keyList = self::getListKey();
        $keyIndex = self::getIndexKey();
        $keyUnread = self::getUnreadKey();
        
        $businessId = $message['business_id'];
        $businessType = $message['business_type'];
        $referenceId = $message['reference_id'];

        // 查找是否存在相同 businessId 的消息
		$existingIndex = Redis::hget($keyIndex, $businessId);

        if (is_numeric($existingIndex) && $existingIndex >= 0) {
            // 更新为 "未读有更新"
            $msg = json_decode(Redis::lindex($keyList, $existingIndex), true);
            $msg['content'] = $message['content'] ?? '';
            if ($msg['status'] == 'read') { 
                $msg['status'] = 'updated';
                Redis::incr($keyUnread); // 只有原来消息已读，才需要增加计数
            } else {
                $msg['status'] = 'updated';
            }
            $msg['timestamp'] = time();
            Redis::lset($keyList, $existingIndex, json_encode($msg));
        } else {
			// 新增消息
            $msg = [
                'id' => (string) Str::uuid(),
				'business_id' => $businessId,
                'business_type' => $businessType,
                'reference_id' => $referenceId,
                'title' => $message['title'] ?? '',
                'content' => $message['content'] ?? '',
                'status' => 'unread',
                'timestamp' => time(),
            ];

            Redis::lpush($keyList, json_encode($msg));
            Redis::hset($keyIndex, $businessId, 0);
            Redis::incr($keyUnread);

            // 重新计算索引（business_id -> index）
            $messages = Redis::lrange($keyList, 0, -1);
            foreach ($messages as $i => $json) {
                $m = json_decode($json, true);
                Redis::hset($keyIndex, $m['business_id'], $i);
            }

            // 限制最多 100 条消息
            Redis::ltrim($keyList, 0, 99);
        }
    }

    /**
     * 获取消息列表
     */
    public static function getMessages()
    {
        $keyList = self::getListKey();
        $messages = Redis::lrange($keyList, 0, -1);

        return array_map(fn($m) => json_decode($m, true), $messages);
    }

    /**
     * 标记为已读
     */
    public static function markAsRead($messageId)
    {
        $keyList = self::getListKey();
        $keyUnread = self::getUnreadKey();

        $messages = Redis::lrange($keyList, 0, -1);

        foreach ($messages as $i => $json) {
            $msg = json_decode($json, true);
            if ($msg['id'] === $messageId && $msg['status'] !== 'read') {
                $msg['status'] = 'read';
                Redis::lset($keyList, $i, json_encode($msg));
                Redis::decr($keyUnread);
                break;
            }
        }
    }

    /**
     * 获取未读数量
     */
    public static function getUnreadCount()
    {
        return (int) (Redis::get(self::getUnreadKey()) ?: 0);
    }
}
