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
     * 更新信息
     */
    public function updateWithdraw(Request $request)
    {
        // 获取传入的更新参数
        $status = $request->input('status', null);  // 状态

        // 查找指定ID的用户
        $withdraw = Withdraw::find($request->id);

        if (!$withdraw) {
            return ApiResponse::error(ApiCode::WITHDRAW_NOT_FOUND);
        }

        // 更新用户信息
        if ($withdraw->status !== $status) {
            $withdraw->status = $status;
        }

        $userAccount = UserAccount::where('user_id', $withdraw->user_id)->first();
        if (!$userAccount) {
            return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
        }
        
        $financeRecord = FinancialRecord::where('reference_id', $withdraw->id)
                ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_WITHDRAW)
                ->first();

        $newWithdraw = DB::transaction(function() use ($withdraw, $financeRecord, $userAccount) {

            $totalExpense = bcadd($withdraw->amount, $withdraw->fee, 2);

            // 如果通过审核，需要进行变动操作
            if ($withdraw->status === BusinessDef::WITHDRAW_APPROVE) {

                // 减钱
                $balanceBefore = $userAccount->total_balance;
                $balanceAfter = bcsub($userAccount->total_balance, $totalExpense, 2);

                // 注意，available余额是不用变动的，因为已经冻结过了
                $userAccount->total_balance = $balanceAfter;

                // 更新信息
                $withdraw->balance_before = $balanceBefore;
                $withdraw->balance_after = $balanceAfter;
                
                // 更新财务变动
                $financeRecord->balance_before = $balanceBefore;
                $financeRecord->balance_after = $balanceAfter;
                $financeRecord->actual_amount = -$totalExpense;
                $financeRecord->status = BusinessDef::TRANSACTION_COMPLETED;
            } else if ($withdraw->status === BusinessDef::WITHDRAW_REJECT) {
                // 驳回需要把冻结的可用资金释放
                $userAccount->available_balance = bcadd($userAccount->available_balance, $totalExpense, 2);

                // 更新财务变动
                $financeRecord->balance_before = $userAccount->total_balance;
                $financeRecord->balance_after = $userAccount->total_balance;
                $financeRecord->actual_amount = 0.00;
                $financeRecord->status = BusinessDef::TRANSACTION_COMPLETED;
            }

            // 无论是否通过，都需要更新充值信息
            $userAccount->save();

            $withdraw->save();

            $financeRecord->save();

            return $withdraw;
        });

        // 添加消息队列
        MessageHelper::pushMessage($withdraw->user_id, [
            'transaction_id' => $financeRecord->transaction_id,
            'transaction_type' => $financeRecord->transaction_type,
            'reference_id' => $financeRecord->reference_id,
            'title' => '',
            'content' => '',
        ]);

        // 通知用户资产变动
        event(new TransactionUpdated(
            $withdraw->user_id,
            $financeRecord->transaction_id,
            $financeRecord->transaction_type,
            $financeRecord->reference_id,
        ));

        return ApiResponse::success([
            'withdraw' => $withdraw
        ]);
    }
}