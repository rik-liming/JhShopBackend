<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\PlatformConfig;

use App\Events\ConfigChanged;

class AdminConfigController extends Controller
{
    /**
     * 获取当前全局配置信息
     */
    public function getConfigInfo(Request $request)
    {
        $config = PlatformConfig::first();

        return ApiResponse::success([
            'config' => $config,
        ]);
    }

    /**
     * 更新配置信息
     */
    public function updateConfig(Request $request)
    {
        // 获取传入的更新参数
        $validated = $request->validate([
            'payment_address' => 'nullable|string',
            'payment_qr_code' => 'nullable|string',
            'transfer_fee' => 'nullable|numeric|min:0',
            'withdrawl_fee' => 'nullable|numeric|min:0',
            'agent_commission_rate' => 'nullable|numeric|min:0',
            'buyer_commission_rate' => 'nullable|numeric|min:0',
            'exchange_rate_platform' => 'nullable|numeric|min:0',
            'exchange_rate_alipay' => 'nullable|numeric|min:0',
            'exchange_rate_wechat' => 'nullable|numeric|min:0',
            'exchange_rate_bank' => 'nullable|numeric|min:0',
            'advertisement_text' => 'nullable|string',
            'remote_order_config' => 'nullable|array'
        ]);

        $config = PlatformConfig::first();

        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }

        // 保存更新
        $config->update($validated);

        event(new ConfigChanged());

        return ApiResponse::success([
            'config' => $config
        ]);
    }
}
