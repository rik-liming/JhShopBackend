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

        // 构建查询
		$query = Transfer::query()->orderBy('id', 'desc');

        if ($sender_user_id) {
            $query->where('sender_user_id', $sender_user_id);
		}
		if ($receiver_user_id) {
            $query->where('receiver_user_id', $receiver_user_id);
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
     * 更新信息
     */
    public function updateTransfer(Request $request)
    {
        // 获取传入的更新参数
        $status = $request->input('status', null);  // 状态

        // 查找指定ID的用户
        $transfer = Transfer::find($request->id);

        if (!$transfer) {
            return ApiResponse::error(ApiCode::TRANSFER_NOT_FOUND);
        }

        // 更新用户信息
        if ($transfer->status !== $status) {
            $transfer->status = $status;
        }

        $senderUserAccount = UserAccount::where('user_id', $transfer->sender_user_id)->first();
		if (!$senderUserAccount) {
			return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
		}

		$receiverUserAccount = UserAccount::where('user_id', $transfer->receiver_user_id)->first();
		if (!$receiverUserAccount) {
			return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
		}
        
        $senderFinanceRecord = FinancialRecord::where('reference_id', $transfer->id)
                ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_TRANSFER_SEND)
				->first();
				
        $receiverFinanceRecord = FinancialRecord::where('reference_id', $transfer->id)
            ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_TRANSFER_RECEIVE)
            ->first();

        $newTransfer = DB::transaction(function() use ($transfer, $senderFinanceRecord, $receiverFinanceRecord,
            $senderUserAccount, $receiverUserAccount) {

            $totalExpense = bcadd($transfer->amount, $transfer->fee, 2);

            // 如果通过审核，需要进行变动操作
            if ($transfer->status === BusinessDef::TRANSFER_APPROVE) {

                // sender减钱
                $senderBalanceBefore = $senderUserAccount->total_balance;
				$senderBalanceAfter = bcsub($senderUserAccount->total_balance, $totalExpense, 2);
				
				// receiver加钱，注意加钱是不包含手续费的
                $receiverBalanceBefore = $receiverUserAccount->total_balance;
                $receiverBalanceAfter = bcadd($receiverUserAccount->total_balance, $transfer->amount, 2);

                // 注意，sender available余额是不用变动的，因为已经冻结过了
                $senderUserAccount->total_balance = $senderBalanceAfter;
				$senderUserAccount->save();
				
				$receiverUserAccount->total_balance = $receiverBalanceAfter;
				$receiverUserAccount->available_balance = bcadd($receiverUserAccount->available_balance, $transfer->amount, 2);
                $receiverUserAccount->save();

                // 更新信息
                $transfer->sender_balance_before = $senderBalanceBefore;
				$transfer->sender_balance_after = $senderBalanceAfter;
				$transfer->receiver_balance_before = $receiverBalanceBefore;
                $transfer->receiver_balance_after = $receiverBalanceAfter;
                
                // 更新财务变动
                $senderFinanceRecord->balance_before = $senderBalanceBefore;
                $senderFinanceRecord->balance_after = $senderBalanceAfter;
                $senderFinanceRecord->actual_amount = -$totalExpense;
                $senderFinanceRecord->status = BusinessDef::TRANSACTION_COMPLETED;
				
				$receiverFinanceRecord->balance_before = $receiverBalanceBefore;
                $receiverFinanceRecord->balance_after = $receiverBalanceAfter;
                $receiverFinanceRecord->actual_amount = $transfer->amount;
                $receiverFinanceRecord->status = BusinessDef::TRANSACTION_COMPLETED;
            } else if ($transfer->status === BusinessDef::TRANSFER_REJECT) {
                // 驳回需要把冻结的可用资金释放
                $senderUserAccount->available_balance = bcadd($senderUserAccount->available_balance, $totalExpense, 2);
                $senderUserAccount->save();

                // 更新财务变动
				$senderFinanceRecord->balance_before = $senderUserAccount->total_balance;
                $senderFinanceRecord->balance_after = $senderUserAccount->total_balance;
                $senderFinanceRecord->actual_amount = 0.00;
                $senderFinanceRecord->status = BusinessDef::TRANSACTION_COMPLETED;

				$receiverFinanceRecord->balance_before = $receiverUserAccount->total_balance;
                $receiverFinanceRecord->balance_after = $receiverUserAccount->total_balance;
                $receiverFinanceRecord->actual_amount = 0.00;
                $receiverFinanceRecord->status = BusinessDef::TRANSACTION_COMPLETED;
            }

            // 无论是否通过，都需要更新充值信息
            $transfer->save();

			$senderFinanceRecord->save();
            $receiverFinanceRecord->save();

            return $transfer;
        });

        // 添加转账者消息队列
        MessageHelper::pushMessage($transfer->sender_user_id, [
            'transaction_id' => $senderFinanceRecord->transaction_id,
            'transaction_type' => $senderFinanceRecord->transaction_type,
            'reference_id' => $senderFinanceRecord->reference_id,
            'title' => '',
            'content' => '',
        ]);

        // 通知用户资产变动
        event(new TransactionUpdated(
            $transfer->sender_user_id,
            $senderFinanceRecord->transaction_id,
            $senderFinanceRecord->transaction_type,
            $senderFinanceRecord->reference_id,
        ));

        // 添加消息队列
        MessageHelper::pushMessage($transfer->receiver_user_id, [
            'transaction_id' => $receiverFinanceRecord->transaction_id,
            'transaction_type' => $receiverFinanceRecord->transaction_type,
            'reference_id' => $receiverFinanceRecord->reference_id,
            'title' => '',
            'content' => '',
        ]);

        // 通知用户资产变动
        event(new TransactionUpdated(
            $transfer->receiver_user_id,
            $receiverFinanceRecord->transaction_id,
            $receiverFinanceRecord->transaction_type,
            $receiverFinanceRecord->reference_id,
        ));

        return ApiResponse::success([
            'transfer' => $transfer
        ]);
    }
}