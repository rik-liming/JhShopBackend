<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Illuminate\Validation\ValidationException;
use App\Exceptions\ApiException;
use App\Enums\ApiCode;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        // 只处理 API 路径
        if ($request->is('api/*')) {

            // 1. 验证异常
            if ($exception instanceof ValidationException) {
                $errors = $exception->errors();
                $firstErrorMsg = array_values($errors)[0][0] ?? 'Validation failed';

                return response()->json([
                    'code' => ApiCode::FIELD_VERIFICATION_FAIL,
                    'msg' => $firstErrorMsg,
                ], 200);
            }

            // 2. 自定义 API 异常
            if ($exception instanceof ApiException) {
                return response()->json([
                    'code' => $exception->getCustomCode(),
                    'msg' => $exception->getCustomMsg(),
                ], 200);
            }

            // 3. 其他异常（调试用）
            return response()->json([
                'code' => 500,
                'msg' => $exception->getMessage(),
            ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
        }

        // 非 API 请求走默认逻辑
        return parent::render($request, $exception);
    }

}
