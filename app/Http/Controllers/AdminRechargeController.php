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
use App\Models\Recharge;
use App\Models\UserAccount;
use App\Models\FinancialRecord;

use App\Events\TransactionUpdated;
use App\Helpers\MessageHelper;

class AdminRechargeController extends Controller
{
    /**
     * 分页获取充值信息
     */
    public function getRechargeByPage(Request $request)
    {
        // 获取分页参数
        $page = $request->input('page', 1);  // 当前页，默认是第1页
        $pageSize = $request->input('page_size', 10);  // 每页显示的记录数，默认是10条
        $user_id = $request->input('user_id', '');  // 搜索关键词，默认空字符串
        $display_recharge_id = $request->input('display_recharge_id', '');  // 搜索关键词，默认空字符串

        $query = Recharge::query()->orderBy('id', 'desc');
        if ($user_id) {
            $query->where('user_id', $user_id);
        }
        if ($display_recharge_id) {
            $query->where('display_recharge_id', $display_recharge_id);
        }

        // 获取符合条件的用户总数
        $totalCount = $query->count();

        // 分页
        $recharges = $query->skip(($page - 1) * $pageSize)  // 计算分页的偏移量
                    ->take($pageSize)  // 每页获取指定数量的用户
                    ->get();

        return ApiResponse::success([
            'total' => $totalCount,  // 总记录数
            'current_page' => $page,  // 当前页
            'page_size' => $pageSize,  // 每页记录数
            'recharges' => $recharges,  // 当前页的用户列表
        ]);
    }

    /**
     * 更新充值信息
     */
    public function updateRecharge(Request $request)
    {
        // 验证输入参数
        $request->validate([
            'id' => 'required|integer',
            'status' => 'required|in:' . implode(',', [
                BusinessDef::RECHARGE_APPROVE,
                BusinessDef::RECHARGE_REJECT,
            ]),
        ], [
            'id.required' => '充值ID不能为空',
            'status.required' => '充值状态不能为空',
        ]);

        $rechargeId = $request->input('id');
        $newStatus = $request->input('status');

        try {
            // 开启事务
            DB::beginTransaction();

            // 锁定充值记录，防止并发修改
            $recharge = Recharge::lockForUpdate()->find($rechargeId);
            if (!$recharge) {
                throw new \Exception('充值记录不存在', ApiCode::RECHARGE_NOT_FOUND);
            }

            $userAccount = UserAccount::lockForUpdate()->where('user_id', $recharge->user_id)->first();
            if (!$userAccount) {
                throw new \Exception('用户账户不存在', ApiCode::USER_ACCOUNT_NOT_FOUND);
            }

            $financeRecord = FinancialRecord::lockForUpdate()
                ->where('reference_id', $recharge->id)
                ->where('transaction_type', BusinessDef::TRANSACTION_TYPE_RECHARGE)
                ->first();
            if (!$financeRecord) {
                throw new \Exception('财务记录不存在', ApiCode::FINANCIAL_RECORD_NOT_FOUND);
            }

            // 更新充值状态及账户
            if ($newStatus !== $recharge->status) {
                $recharge->status = $newStatus;

                if ($newStatus === BusinessDef::RECHARGE_APPROVE) {
                    $balanceBefore = $userAccount->total_balance;
                    $balanceAfter = bcadd($balanceBefore, $recharge->amount, 2);

                    $userAccount->total_balance = $balanceAfter;
                    $userAccount->available_balance = bcadd($userAccount->available_balance, $recharge->amount, 2);
                    $userAccount->save();

                    $recharge->balance_before = $balanceBefore;
                    $recharge->balance_after = $balanceAfter;

                    $financeRecord->balance_before = $balanceBefore;
                    $financeRecord->balance_after = $balanceAfter;
                    $financeRecord->actual_amount = $recharge->amount;
                    $financeRecord->status = BusinessDef::TRANSACTION_COMPLETED;
                } elseif ($newStatus === BusinessDef::RECHARGE_REJECT) {
                    $financeRecord->balance_before = $userAccount->total_balance;
                    $financeRecord->balance_after = $userAccount->total_balance;
                    $financeRecord->actual_amount = 0.00;
                    $financeRecord->status = BusinessDef::TRANSACTION_COMPLETED;
                }

                $recharge->save();
                $financeRecord->save();
            }

            // 提交事务
            DB::commit();

            // 推送消息通知用户
            MessageHelper::pushMessage($recharge->user_id, [
                'transaction_id' => $financeRecord->transaction_id,
                'transaction_type' => $financeRecord->transaction_type,
                'reference_id' => $financeRecord->reference_id,
                'title' => '',
                'content' => '',
            ]);

            // 通知资产变动
            event(new TransactionUpdated(
                $recharge->user_id,
                $financeRecord->transaction_id,
                $financeRecord->transaction_type,
                $financeRecord->reference_id,
            ));

            return ApiResponse::success([
                'recharge' => $recharge,
            ]);

        } catch (\Exception $e) {
            // 回滚事务
            DB::rollBack();

            // 抛出自定义异常给统一处理
            return ApiResponse::error($e->getCode());
        }
    }


}