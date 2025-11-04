<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Helpers\AdminMessageHelper;

class AdminMessageController extends Controller
{
    // 获取消息列表 + 未读数量
    public function getList(Request $request)
    {

		$messages = AdminMessageHelper::getMessages();
		$unread_count = AdminMessageHelper::getUnreadCount();
		
		return ApiResponse::success([
			'messages' => $messages,
			'unread_count' => $unread_count,
		]);
	}
	
	// 获取未读数量
    public function getUnreadCount(Request $request)
    {
		$unread_count = AdminMessageHelper::getUnreadCount();
		
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

		$id = $request->id;

        AdminMessageHelper::markAsRead($id);
        return ApiResponse::success([]);
    }
}
