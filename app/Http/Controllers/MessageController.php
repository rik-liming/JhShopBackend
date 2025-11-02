<?php

namespace App\Http\Controllers;

use App\Helpers\MessageHelper;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;

class MessageController extends Controller
{
    // 获取消息列表 + 未读数量
    public function getList(Request $request)
    {
        // 从中间件获取的用户ID
		$userId = $request->user_id_from_token ?? null;

		$messages = MessageHelper::getMessages($userId);
		$unread_count = MessageHelper::getUnreadCount($userId);
		
		return ApiResponse::success([
			'messages' => $messages,
			'unread_count' => $unread_count,
		]);
	}
	
	// 获取未读数量
    public function getUnreadCount(Request $request)
    {
        // 从中间件获取的用户ID
		$userId = $request->user_id_from_token ?? null;

		$unread_count = MessageHelper::getUnreadCount($userId);
		
		return ApiResponse::success([
			'unread_count' => $unread_count,
		]);
    }

    // 标记消息为已读
    public function markAsRead(Request $request)
    {
		$request->validate([
            'id' => 'required',
        ], [
            'id.required' => '消息ID不能为空',
        ]);

        // 从中间件获取的用户ID
		$userId = $request->user_id_from_token ?? null;
		$id = $request->id;

        MessageHelper::markAsRead($userId, $id);
        return ApiResponse::success([]);
    }

    // 模拟推送一条新消息（测试用）
    public function test(Request $request)
    {
        MessageHelper::pushMessage(1234, [
			'transaction_id' => 1234554,
			'transaction_type' => 'order_buy',
			'reference_id' => 1124,
			'title' => '',
			'content' => '',
		]);

        return response()->json(['message' => '推送成功']);
    }
}
