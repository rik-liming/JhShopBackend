<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Enums\BusinessDef;

use App\Models\User;
use App\Models\Transfer;
use App\Models\UserAccount;
use App\Models\FinancialRecord;

use App\Events\TransactionUpdated;
use App\Events\AdminReddotUpdated;
use App\Helpers\MessageHelper;

class AdminTransferController extends Controller
{
    /**
     * 分页获取信息
     */
    public function getTransferByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 10);  // 每页显示的记录数，默认是10条
		$sender_user_id = $request->input('sender_user_id', '');  // 搜索关键词，默认空字符串
        $receiver_user_id = $request->input('receiver_user_id', '');  // 搜索关键词，默认空字符串
        $display_transfer_id = $request->input('display_transfer_id', '');  // 搜索关键词，默认空字符串

        // 构建查询
		$query = Transfer::query()->orderBy('id', 'desc');

        if ($sender_user_id) {
            $query->where('sender_user_id', $sender_user_id);
		}
		if ($receiver_user_id) {
            $query->where('receiver_user_id', $receiver_user_id);
        }
        if ($display_transfer_id) {
            $query->where('display_transfer_id', $display_transfer_id);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $transfers = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'transfers' => $transfers,  // 当前页的用户列表
        ]);
    }

    /**
     * 更新转账信息（审核）
     */
    public function updateTransfer(Request $request)
    {
        // 参数校验
        $request->validate([
            'id' => 'required|integer',
            'status' => 'required|in:' . implode(',', [
                BusinessDef::TRANSFER_APPROVE,
                BusinessDef::TRANSFER_REJECT,
            ]),
        ], [
            'id.required' => '转账ID不能为空',
            'status.required' => '转账状态不能为空',
        ]);

        $transferId = $request->input('id');
        $newStatus = $request->input('status');

        try {
            DB::beginTransaction();

            // 锁定转账记录
            $transfer = Transfer::lockForUpdate()->find($transferId);
            if (!$transfer) {
                throw new \Exception("转账记录不存在", ApiCode::TRANSFER_NOT_FOUND);
            }

            // 锁定账户
            $senderAccount = UserAccount::where('user_id', $transfer->sender_user_id)->lockForUpdate()->first();
            $receiverAccount = UserAccount::where('user_id', $transfer->receiver_user_id)->lockForUpdate()->first();
            if (!$senderAccount || !$receiverAccount) {
                throw new \Exception("用户账户不存在", ApiCode::USER_ACCOUNT_NOT_FOUND);
            }

            // 锁定财务记录
            $senderFinance = FinancialRecord::where('reference_id', $transfer->id)
                ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_TRANSFER_SEND)
                ->lockForUpdate()->first();

            $receiverFinance = FinancialRecord::where('reference_id', $transfer->id)
                ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_TRANSFER_RECEIVE)
                ->lockForUpdate()->first();

            if (!$senderFinance || !$receiverFinance) {
                throw new \Exception("财务记录不存在", ApiCode::FINANCIAL_RECORD_NOT_FOUND);
            }

            // 幂等：只有状态变化才执行
            if ($transfer->status !== $newStatus) {

                $transfer->status = $newStatus;
                $totalExpense = bcadd($transfer->amount, $transfer->fee, 2);

                if ($newStatus === BusinessDef::TRANSFER_APPROVE) {

                    // 扣除发送者余额
                    $senderBefore = $senderAccount->total_balance;
                    $senderAfter = bcsub($senderBefore, $totalExpense, 2);
                    $senderAccount->total_balance = $senderAfter;
                    $senderAccount->save();

                    // 给接收者加钱
                    $receiverBefore = $receiverAccount->total_balance;
                    $receiverAfter = bcadd($receiverBefore, $transfer->amount, 2);
                    $receiverAccount->total_balance = $receiverAfter;
                    $receiverAccount->available_balance = bcadd($receiverAccount->available_balance, $transfer->amount, 2);
                    $receiverAccount->save();

                    // 更新转账数据
                    $transfer->sender_balance_before = $senderBefore;
                    $transfer->sender_balance_after = $senderAfter;
                    $transfer->receiver_balance_before = $receiverBefore;
                    $transfer->receiver_balance_after = $receiverAfter;

                    // 更新财务记录
                    $senderFinance->balance_before = $senderBefore;
                    $senderFinance->balance_after = $senderAfter;
                    $senderFinance->actual_amount = -$totalExpense;
                    $senderFinance->status = BusinessDef::TRANSACTION_COMPLETED;

                    $receiverFinance->balance_before = $receiverBefore;
                    $receiverFinance->balance_after = $receiverAfter;
                    $receiverFinance->actual_amount = $transfer->amount;
                    $receiverFinance->status = BusinessDef::TRANSACTION_COMPLETED;

                } elseif ($newStatus === BusinessDef::TRANSFER_REJECT) {

                    // 解冻发送者余额
                    $senderAccount->available_balance = bcadd($senderAccount->available_balance, $totalExpense, 2);
                    $senderAccount->save();

                    // 更新财务记录
                    $senderFinance->balance_before = $senderAccount->total_balance;
                    $senderFinance->balance_after = $senderAccount->total_balance;
                    $senderFinance->actual_amount = 0.00;
                    $senderFinance->status = BusinessDef::TRANSACTION_COMPLETED;

                    $receiverFinance->balance_before = $receiverAccount->total_balance;
                    $receiverFinance->balance_after = $receiverAccount->total_balance;
                    $receiverFinance->actual_amount = 0.00;
                    $receiverFinance->status = BusinessDef::TRANSACTION_COMPLETED;
                }

                // 保存
                $transfer->save();
                $senderFinance->save();
                $receiverFinance->save();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("[updateTransfer] " . $e->getMessage());

            return ApiResponse::error($e->getCode() ?: ApiCode::OPERATION_FAIL);
        }

        /**
         * 幂等消息推送
         */
        foreach ([
            [$transfer->sender_user_id, $senderFinance],
            [$transfer->receiver_user_id, $receiverFinance]
        ] as [$userId, $finance]) {

            MessageHelper::pushMessage($userId, [
                'transaction_id' => $finance->transaction_id,
                'transaction_type' => $finance->transaction_type,
                'reference_id' => $finance->reference_id,
                'title' => '',
                'content' => '',
            ]);

            event(new TransactionUpdated(
                $userId,
                $finance->transaction_id,
                $finance->transaction_type,
                $finance->reference_id,
            ));
        }

        // 通知红点变更
        event(new AdminReddotUpdated());

        return ApiResponse::success([
            'transfer' => $transfer
        ]);
    }

}