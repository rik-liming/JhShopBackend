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

    public static function clearReadMessages()
    {
        $keyList = self::getListKey();
        $keyIndex = self::getIndexKey();
        $keyUnread = self::getUnreadKey();

        // 读取全部消息
        $messages = Redis::lrange($keyList, 0, -1);

        if (empty($messages)) {
            // 无消息直接清空索引和未读
            Redis::del($keyIndex);
            Redis::set($keyUnread, 0);
            return;
        }

        // 过滤掉所有 status = read 的消息
        $filtered = [];
        foreach ($messages as $msgJson) {
            $msg = json_decode($msgJson, true);
            if (!isset($msg['status']) || $msg['status'] !== 'read') {
                $filtered[] = $msgJson;
            }
        }

        // 重建消息列表
        Redis::del($keyList);  
        if (!empty($filtered)) {
            // lpush 会反向，所以使用 rpush 保持原顺序
            Redis::rpush($keyList, ...$filtered);
        }

        // 重新生成 business_id → index 映射
        Redis::del($keyIndex);

        $newUnreadCount = 0;

        $newList = Redis::lrange($keyList, 0, -1);
        foreach ($newList as $i => $json) {
            $m = json_decode($json, true);

            if (isset($m['business_id'])) {
                Redis::hset($keyIndex, $m['business_id'], $i);
            }

            // 统计未读或 updated（因为 updated 也属于未处理）
            if (!isset($m['status']) || $m['status'] === 'unread' || $m['status'] === 'updated') {
                $newUnreadCount++;
            }
        }

        // 更新未读计数
        Redis::set($keyUnread, $newUnreadCount);
    }
}
