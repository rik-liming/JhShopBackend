<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class MessageHelper
{
    protected static function getListKey($userId)
    {
        return "user:{$userId}:messages";
    }

    protected static function getIndexKey($userId)
    {
        return "user:{$userId}:message_index";
    }

    protected static function getUnreadKey($userId)
    {
        return "user:{$userId}:unread_count";
    }

    /**
     * 添加消息
     */
    public static function pushMessage($userId, array $message)
    {
        $keyList = self::getListKey($userId);
        $keyIndex = self::getIndexKey($userId);
		$keyUnread = self::getUnreadKey($userId);

		$transactionId = $message['transaction_id'];
		$transactionType = $message['transaction_type'];
		$referenceId = $message['reference_id'];

        // 查找是否存在相同 transactionId 的消息
		$existingIndex = Redis::hget($keyIndex, $transactionId);

        if (is_numeric($existingIndex) && $existingIndex >= 0) {
            // 更新为 "未读有更新"
            $msg = json_decode(Redis::lindex($keyList, $existingIndex), true);
            $msg['content'] = $message['content'] ?? '';
            if ($msg['status'] == 'read') {
                $msg['status'] = 'updated';
            }
            $msg['timestamp'] = time();
            Redis::lset($keyList, $existingIndex, json_encode($msg));
            Redis::incr($keyUnread);
        } else {
			// 新增消息
            $msg = [
                'id' => (string) Str::uuid(),
				'transaction_id' => $transactionId,
				'transaction_type' => $transactionType,
				'reference_id' => $referenceId,
                'title' => $message['title'] ?? '',
                'content' => $message['content'] ?? '',
                'status' => 'unread',
                'timestamp' => time(),
            ];

            Redis::lpush($keyList, json_encode($msg));
            Redis::hset($keyIndex, $transactionId, 0);
            Redis::incr($keyUnread);

            // 重新计算索引（transaction_id -> index）
            $messages = Redis::lrange($keyList, 0, -1);
            foreach ($messages as $i => $json) {
                $m = json_decode($json, true);
                Redis::hset($keyIndex, $m['transaction_id'], $i);
            }

            // 限制最多 100 条消息
            Redis::ltrim($keyList, 0, 99);
        }
    }

    /**
     * 获取消息列表
     */
    public static function getMessages($userId)
    {
        $keyList = self::getListKey($userId);
        $messages = Redis::lrange($keyList, 0, -1);

        return array_map(fn($m) => json_decode($m, true), $messages);
    }

    /**
     * 标记为已读
     */
    public static function markAsRead($userId, $messageId)
    {
        $keyList = self::getListKey($userId);
        $keyUnread = self::getUnreadKey($userId);

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
    public static function getUnreadCount($userId)
    {
        return (int) (Redis::get(self::getUnreadKey($userId)) ?: 0);
    }
}
