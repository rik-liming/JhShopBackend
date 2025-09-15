<?php

namespace App\Exceptions;

use Exception;
use App\Enums\ApiCode;

class ApiException extends \Exception
{
    protected int $customCode;

    public function __construct(int $customCode = ApiCode::SERVER_ERROR)
    {
        $this->customCode = $customCode;
    }

    public function getCustomCode()
    {
        return $this->customCode;
    }

    public function getCustomMsg()
    {
        return ApiCode::$msg[$this->customCode];
    }
}
