<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\ApiResponse;
use App\Enums\ApiCode;
use App\Models\PlatformConfig;

class ConfigController extends Controller
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
}
