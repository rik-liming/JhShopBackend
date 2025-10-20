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

use App\Models\User;
use App\Models\Recharge;
use App\Models\UserAccount;
use App\Models\FinancialRecord;

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

        // 获取关键词和角色过滤参数
        $keyword = $request->input('keyword', '');  // 搜索关键词，默认空字符串

        // 构建查询
        $query = Recharge::where('status', '!=', -1);  // 过滤掉status为-1的用户

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
        // 获取传入的更新参数
        $status = $request->input('status', null);  // 状态

        // 查找指定ID的用户
        $recharge = Recharge::find($request->id);

        if (!$recharge) {
            return ApiResponse::error(ApiCode::RECHARGE_NOT_FOUND);
        }

        // 更新用户信息
        if ($status !== $recharge->status) {
            $recharge->status = $status;
        }

        $userAccount = null;
        if ($status === 1) {
            $userAccount = UserAccount::where('user_id', $recharge->user_id)->first();
            if (!$userAccount) {
                return ApiResponse::error(ApiCode::USER_ACCOUNT_NOT_FOUND);
            }
        }
        
        $newRecharge = DB::transaction(function() use ($recharge, $userAccount) {

            $financeRecord = FinancialRecord::where('reference_id', $recharge->id)
                ->first();

            // 如果通过审核，需要进行变动操作
            if ($recharge->status === 1) {

                // 加钱
                $balanceBefore = $userAccount->total_balance;
                $balanceAfter = bcadd($userAccount->total_balance, $recharge->amount, 2);

                $userAccount->total_balance = $balanceAfter;
                $userAccount->available_balance = bcadd($userAccount->available_balance, $recharge->amount, 2);
                $userAccount->save();

                // 更新充值信息
                $recharge->balance_before = $balanceBefore;
                $recharge->balance_after = $balanceAfter;
                
                // 更新财务变动
                $financeRecord->balance_before = $balanceBefore;
                $financeRecord->balance_after = $balanceAfter;
            } else if ($recharge->status === -1) {
                // 更新财务变动
                $financeRecord->balance_before = $userAccount->total_balance;
                $financeRecord->balance_after = $userAccount->total_balance;
            }

            // 无论是否通过，都需要更新充值信息
            $recharge->save();

            $financeRecord->save();

            return $recharge;
        });

        return ApiResponse::success([
            'recharge' => $recharge
        ]);
    }
}