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
    const USER_ILLEGAL = 20005;
    const USER_BALANCE_NOT_ENOUGH = 20006;
    const USER_ACCOUNT_NOT_FOUND = 20007;
    const USER_PAYMENT_PASSWORD_NOT_SET = 20008;
    const USER_PAYMENT_PASSWORD_WRONG = 20009;
    const USER_PAYMENT_METHOD_NOT_FOUND = 20010;
    const USER_PAYMENT_METHOD_NOT_SET = 20011;

    // 订单相关
    const ORDER_LISTING_NOT_FOUND = 20100;
    const ORDER_LISTING_AMOUNT_NOT_ENOUGH = 20101;
    const ORDER_LISTING_MIN_SALE_AMOUNT_LIMIT = 20102;
    const ORDER_CREATE_FAIL = 20103;
    const ORDER_NOT_FOUND = 20104;
    const ORDER_CONFIRM_FAIL = 20105;

    // 交易相关
    const TRANSFER_NOT_FOUND = 20200;
    const RECHARGE_NOT_FOUND = 20201;
    const WITHDRAW_NOT_FOUND = 20202;

    // 配置相关
    const CONFIG_NOT_FOUND = 20300;

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
        self::USER_ILLEGAL => '非法用户',
        self::USER_BALANCE_NOT_ENOUGH => '用户余额不足',
        self::USER_ACCOUNT_NOT_FOUND => '用户余额账户不存在',
        self::USER_PAYMENT_PASSWORD_NOT_SET => '支付密码未设置',
        self::USER_PAYMENT_PASSWORD_WRONG => '支付密码错误',
        self::USER_PAYMENT_METHOD_NOT_FOUND => '未找到该支付方式',
        self::USER_PAYMENT_METHOD_NOT_SET => '未设置收款信息',

        self::ORDER_LISTING_NOT_FOUND => '无该挂单信息',
        self::ORDER_LISTING_AMOUNT_NOT_ENOUGH => '挂单库存不足',
        self::ORDER_LISTING_MIN_SALE_AMOUNT_LIMIT => '不满足挂单最低购买数量',
        self::ORDER_CREATE_FAIL => '订单创建失败',
        self::ORDER_NOT_FOUND => '订单不存在',
        self::ORDER_CONFIRM_FAIL => '订单确认失败',

        self::TRANSFER_NOT_FOUND => '转账记录不存在',
        self::RECHARGE_NOT_FOUND => '充值记录不存在',
        self::WITHDRAW_NOT_FOUND => '提现记录不存在',

        self::CONFIG_NOT_FOUND => '配置不存在',

        self::ADMIN_NAME_PASSWORD_WRONG => '用户名或密码错误',
        self::ADMIN_NOT_FOUND => '账号不存在',
        self::ADMIN_2FA_INVALID => '验证码错误',
    ];
}
