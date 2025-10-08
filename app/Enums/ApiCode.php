<?php

namespace App\Enums;

class ApiCode
{
    // 成功
    const SUCCESS = 10000;

    // 全局相关
    const FIELD_VERIFICATION_FAIL = 10001;
    const LOGIN_TOKEN_INVALID = 10002;

    // 用户相关
    const INVALID_INVITE_CODE = 20001;
    const USER_EMAIL_PASSWORD_WRONG = 20002;
    const USER_NOT_FOUND = 20003;
    const USER_2FA_INVALID = 20004;

    // admin相关
    const ADMIN_NAME_PASSWORD_WRONG = 30001;
    const ADMIN_NOT_FOUND = 30002;
    const ADMIN_2FA_INVALID = 30003;

    public static $msg = [
        self::SUCCESS => '成功',

        self::FIELD_VERIFICATION_FAIL => '字段校验失败',
        self::LOGIN_TOKEN_INVALID => 'token无效',

        self::INVALID_INVITE_CODE => '邀请码无效',
        self::USER_EMAIL_PASSWORD_WRONG => '邮箱或密码错误',
        self::USER_NOT_FOUND => '用户不存在',
        self::USER_2FA_INVALID => '验证码错误',

        self::ADMIN_NAME_PASSWORD_WRONG => '用户名或密码错误',
        self::ADMIN_NOT_FOUND => '账号不存在',
        self::ADMIN_2FA_INVALID => '验证码错误',
    ];
}
