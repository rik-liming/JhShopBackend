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
            'transfer_fee' => 'nullable|numeric|min:0',
            'withdrawl_fee' => 'nullable|numeric|min:0',
            'agent_commission_rate' => 'nullable|numeric|min:0',
            'buyer_commission_rate' => 'nullable|numeric|min:0',
            'exchange_rate_platform' => 'nullable|numeric|min:0',
            'exchange_rate_alipay' => 'nullable|numeric|min:0',
            'exchange_rate_wechat' => 'nullable|numeric|min:0',
            'exchange_rate_bank' => 'nullable|numeric|min:0',
            'exchange_rate_ecny' => 'nullable|numeric|min:0',
            'advertisement_text' => 'nullable|string',
            'remote_order_config' => 'nullable|string'
        ]);

        $config = PlatformConfig::first();

        if (!$config) {
            return ApiResponse::error(ApiCode::CONFIG_NOT_FOUND);
        }

        // 保存截图
        $payment_qr_code_changed = $request->input('payment_qr_code_changed');
        if ($payment_qr_code_changed === true || $payment_qr_code_changed === 'true') {
            $image = $request->file('payment_qr_code_image');
            if ($image) {
                $imageDirectory = '/data/images';
                if (!file_exists($imageDirectory)) {
                    mkdir($imageDirectory, 0777, true);
                }
                $imageName = 'platform_' . date('YmdHis') . '.' . $image->getClientOriginalExtension();
                $image->move($imageDirectory, $imageName);
                $payment_qr_code = $imageDirectory . '/' . $imageName;
                $validated['payment_qr_code'] = $payment_qr_code;
            } else {
                $validated['payment_qr_code'] = '';
            }
        } else {
            $validated['payment_qr_code'] = $config->payment_qr_code;
        }
        
        $validated['remote_order_config'] = json_decode($validated['remote_order_config'], true);

        // 保存更新
        $config->update($validated);

        event(new ConfigChanged());

        return ApiResponse::success([
            'config' => $config
        ]);
    }
}
