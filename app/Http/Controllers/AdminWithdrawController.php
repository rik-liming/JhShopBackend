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
use App\Models\Withdraw;
use App\Models\UserAccount;
use App\Models\FinancialRecord;

use App\Events\TransactionUpdated;
use App\Events\AdminReddotUpdated;
use App\Helpers\MessageHelper;

class AdminWithdrawController extends Controller
{
    /**
     * 分页获取信息
     */
    public function getWithdrawByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 10);  // 每页显示的记录数，默认是10条
        $user_id = $request->input('user_id', '');  // 搜索关键词，默认空字符串
        $display_withdraw_id = $request->input('display_withdraw_id', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = Withdraw::query()->orderBy('id', 'desc');

        if ($user_id) {
            $query->where('user_id', $user_id);
        }
        if ($display_withdraw_id) {
            $query->where('display_withdraw_id', $display_withdraw_id);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $withdraws = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'withdraws' => $withdraws,  // 当前页的用户列表
        ]);
    }

    /**
     * 更新提现信息（管理员使用）
     */
    public function updateWithdraw(Request $request)
    {
        // 参数校验
        $request->validate([
            'id' => 'required|integer',
            'status' => 'required|in:' . implode(',', [
                BusinessDef::WITHDRAW_APPROVE,
                BusinessDef::WITHDRAW_REJECT,
            ]),
        ], [
            'id.required' => '提现ID不能为空',
            'status.required' => '提现状态不能为空',
        ]);

        $withdrawId = $request->input('id');
        $newStatus = $request->input('status');

        try {
            DB::beginTransaction();

            // 锁提现记录
            $withdraw = Withdraw::lockForUpdate()->find($withdrawId);
            if (!$withdraw) throw new ApiException(ApiCode::WITHDRAW_NOT_FOUND);

            // 锁用户账户
            $userAccount = UserAccount::lockForUpdate()
                ->where('user_id', $withdraw->user_id)
                ->first();
            if (!$userAccount) throw new ApiException(ApiCode::USER_ACCOUNT_NOT_FOUND);

            // 锁财务记录
            $financeRecord = FinancialRecord::lockForUpdate()
                ->where('reference_id', $withdraw->id)
                ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_WITHDRAW)
                ->first();
            if (!$financeRecord) throw new ApiException(ApiCode::FINANCIAL_RECORD_NOT_FOUND);

            // 幂等处理
            if ($withdraw->status !== $newStatus) {
                $withdraw->status = $newStatus;
                $totalExpense = bcadd($withdraw->amount, $withdraw->fee, 2);

                if ($newStatus === BusinessDef::WITHDRAW_APPROVE) {
                    // 审核通过，扣除总余额
                    $before = $userAccount->total_balance;
                    $after  = bcsub($before, $totalExpense, 2);

                    $userAccount->total_balance = $after;

                    $withdraw->balance_before = $before;
                    $withdraw->balance_after  = $after;

                    $financeRecord->balance_before = $before;
                    $financeRecord->balance_after  = $after;
                    $financeRecord->actual_amount  = -$totalExpense;
                    $financeRecord->status         = BusinessDef::TRANSACTION_COMPLETED;

                } elseif ($newStatus === BusinessDef::WITHDRAW_REJECT) {
                    // 驳回，返还冻结资金到 available_balance
                    $userAccount->available_balance = bcadd($userAccount->available_balance, $totalExpense, 2);

                    // 财务记录更新
                    $financeRecord->balance_before = $userAccount->total_balance;
                    $financeRecord->balance_after  = $userAccount->total_balance;
                    $financeRecord->actual_amount  = 0.00;
                    $financeRecord->status         = BusinessDef::TRANSACTION_COMPLETED;
                }

                $userAccount->save();
                $withdraw->save();
                $financeRecord->save();
            }

            DB::commit();

            // 返回给事务外，因为要用于推送和事件
            $updatedWithdraw = $withdraw;

        } catch (ApiException $e) {
            DB::rollBack();
            return ApiResponse::error($e->getCode());
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[updateWithdraw] '.$e->getMessage());
            return ApiResponse::error(ApiCode::OPERATION_FAIL);
        }

        // -------- 事务外发送消息通知（幂等） --------

        MessageHelper::pushMessage($updatedWithdraw->user_id, [
            'transaction_id' => $financeRecord->transaction_id,
            'transaction_type' => $financeRecord->transaction_type,
            'reference_id' => $financeRecord->reference_id,
            'title' => '',
            'content' => '',
        ]);

        event(new TransactionUpdated(
            $updatedWithdraw->user_id,
            $financeRecord->transaction_id,
            $financeRecord->transaction_type,
            $financeRecord->reference_id,
        ));

        // 通知红点变更
        event(new AdminReddotUpdated());

        return ApiResponse::success([
            'withdraw' => $updatedWithdraw
        ]);
    }


}