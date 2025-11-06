<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Enums\BusinessDef;

use App\Models\User;
use App\Models\Recharge;
use App\Models\Transfer;
use App\Models\Withdraw;
use App\Models\Order;

class AdminReddotController extends Controller
{
    // 获取红点数据
    public function getReddot(Request $request)
    {
        $reddot = [];
        $reddot['user'] = User::where('role', BusinessDef::USER_ROLE_DEFAULT)->count();

        $rechargeReddotCount = Recharge::where('status', BusinessDef::RECHARGE_WAIT)->count();
        $transferReddotCount = Transfer::where('status', BusinessDef::TRANSFER_WAIT)->count();
        $withdrawReddotCount = Withdraw::where('status', BusinessDef::WITHDRAW_WAIT)->count();
        $transactionReddotCount = $rechargeReddotCount + $transferReddotCount + $withdrawReddotCount;

        $reddot['recharge'] = $rechargeReddotCount;
        $reddot['transfer'] = $transferReddotCount;
        $reddot['withdraw'] = $withdrawReddotCount;
        $reddot['transaction'] = $transactionReddotCount;

        $normalOrderReddotCount = Order::where('type', BusinessDef::ORDER_TYPE_NORMAL)
            ->where('status', BusinessDef::ORDER_STATUS_ARGUE)->count();
        
        $autoOrderReddotCount = Order::where('type', BusinessDef::ORDER_TYPE_AUTO)
            ->where('status', BusinessDef::ORDER_STATUS_ARGUE)->count();

        $reddot['order_normal'] = $normalOrderReddotCount;
        $reddot['order_auto'] = $autoOrderReddotCount;
        $reddot['order'] = $normalOrderReddotCount + $autoOrderReddotCount;

        return ApiResponse::success([
            'reddot' => $reddot
        ]);
    }
}