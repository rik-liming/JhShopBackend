<?php

namespace App\Helpers;

use App\Enums\ApiCode;

class ApiResponse
{
    public static function success($data = [], $code = ApiCode::SUCCESS)
    {
        return response()->json([
            'code' => $code,
            'msg'  => ApiCode::$msg[$code],
            'data' => $data
        ]);
    }

    public static function error($code, $data = null, $extraMsg = null)
    {
        return response()->json([
            'code' => $code,
            'msg'  => $extraMsg ?? ApiCode::$msg[$code],
            'data' => $data
        ]);
    }
}
