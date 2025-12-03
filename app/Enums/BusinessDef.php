<?php

namespace App\Enums;

class BusinessDef
{
    const RECHARGE_WAIT = 0;     // 充值申请待处理
    const RECHARGE_APPROVE = 1;  // 充值申请通过
    const RECHARGE_REJECT = -1;  // 充值申请驳回

    const WITHDRAW_WAIT = 0;     // 提现申请待处理
    const WITHDRAW_APPROVE = 1;  // 提现申请通过
    const WITHDRAW_REJECT = -1;  // 提现申请驳回

    const TRANSFER_WAIT = 0;     // 转账申请待处理
    const TRANSFER_APPROVE = 1;  // 转账申请通过
    const TRANSFER_REJECT = -1;  // 转账申请驳回

    const TRANSACTION_ONGOING = 0;  // 财务变动进行中
    const TRANSACTION_COMPLETED = 1;  // 财务变动已完结

    const PAYMENT_METHOD_NOT_DEFAULT = 0;  // 支付方式非默认
    const PAYMENT_METHOD_IS_DEFAULT = 1;  // 支付方式默认

    const PAYMENT_METHOD_INACTIVE = 0;  // 支付方式禁用
    const PAYMENT_METHOD_ACTIVE = 1;  // 支付方式启用
    const PAYMENT_METHOD_DELETED = -1;  // 支付方式删除

    const TRANSACTION_TYPE_RECHARGE = 'recharge';  // 交易类型：充值
    const TRANSACTION_TYPE_WITHDRAW = 'withdraw';  // 交易类型：提现
    const TRANSACTION_TYPE_TRANSFER_SEND = 'transfer_send';  // 交易类型：转账
    const TRANSACTION_TYPE_TRANSFER_RECEIVE = 'transfer_receive';  // 交易类型：入账
    const TRANSACTION_TYPE_ORDER_BUY = 'order_buy';  // 交易类型：买入
    const TRANSACTION_TYPE_ORDER_SELL = 'order_sell';  // 交易类型：卖出
    const TRANSACTION_TYPE_ORDER_AUTO_BUY = 'order_auto_buy';  // 交易类型：自动化买入
    const TRANSACTION_TYPE_ORDER_AUTO_SELL = 'order_auto_sell';  // 交易类型：自动化卖出

    const PAYMENT_METHOD_ALIPAY = 'alipay'; // 支付方式：alipay
    const PAYMENT_METHOD_WECHAT = 'wechat'; // 支付方式：wechat
    const PAYMENT_METHOD_BANK = 'bank';   // 支付方式：bank
    const PAYMENT_METHOD_ECNY = 'ecny';   // 支付方式：ecny

    const ORDER_STATUS_WAIT_BUYER = 0;   // 订单状态：等待买家确认
    const ORDER_STATUS_WAIT_SELLER = 1;  // 订单状态：等待卖家确认
    const ORDER_STATUS_COMPLETED = 2;   // 订单状态：已完成
    const ORDER_STATUS_EXPIRED = 3;   // 订单状态：超时
    const ORDER_STATUS_ARGUE = 4;   // 订单状态：争议待处理
    const ORDER_STATUS_ARGUE_APPROVE = 5;   // 订单状态：争议通过
    const ORDER_STATUS_ARGUE_REJECT = 6;   // 订单状态：争议驳回

    const ORDER_TYPE_NORMAL = 'normal';   // 订单类型：普通订单
    const ORDER_TYPE_AUTO = 'auto';       // 订单类型：自动化订单

    const ORDER_LISTING_STATUS_OFFSELL = 0;   // 挂单状态：下架
    const ORDER_LISTING_STATUS_ONLINE = 1;   // 挂单状态：在售
    const ORDER_LISTING_STATUS_FROBIDDEN = 2;   // 挂单状态：禁售
    const ORDER_LISTING_STATUS_STOCK_LOCK = 3;   // 挂单状态：锁库存冻结
    const ORDER_LISTING_STATUS_SELL_OUT = 4;   // 挂单状态：售完
    const ORDER_LISTING_STATUS_CANCEL = 5;   // 挂单状态：撤单

    const USER_ROLE_DEFAULT = 'default';   // 用户角色：默认
    const USER_ROLE_PLATFORM = 'platform';   // 用户角色：平台总代理
    const USER_ROLE_AGENT = 'agent';   // 用户角色：代理
    const USER_ROLE_SELLER = 'seller';   // 用户角色：商户
    const USER_ROLE_BUYER = 'buyer';   // 用户角色：买家
    const USER_ROLE_AUTO_BUYER = 'autoBuyer';   // 用户角色：自动化买家

    const USER_STATUS_INACTIVE = 0;   // 用户状态：封禁
    const USER_STATUS_ACTIVE = 1;   // 用户状态：正常
    const USER_STATUS_DELETED = -1;   // 用户状态：删除

    const ADMIN_BUSINESS_TYPE_REGISTER = 'register';   // 管理员需处理业务类型：注册
    const ADMIN_BUSINESS_TYPE_RECHARGE = 'recharge';   // 管理员需处理业务类型：充值
    const ADMIN_BUSINESS_TYPE_TRANSFER = 'transfer';   // 管理员需处理业务类型：转账
    const ADMIN_BUSINESS_TYPE_WITHDRAW = 'withdraw';   // 管理员需处理业务类型：提现
    const ADMIN_BUSINESS_TYPE_ORDER_ARGUE = 'order_argue';   // 管理员需处理业务类型：订单争议
    const ADMIN_BUSINESS_TYPE_AUTO_ORDER_ARGUE = 'auto_order_argue';   // 管理员需处理业务类型：自动化订单争议

    const ADMIN_ROLE_STATUS_INACTIVE = 0;   // 管理员角色状态：封禁
    const ADMIN_ROLE_STATUS_ACTIVE = 1;   // 管理员角色状态：正常
    const ADMIN_ROLE_STATUS_DELETED = -1;   // 管理员角色状态：删除

    const ADMIN_STATUS_INACTIVE = 0;   // 管理员状态：封禁
    const ADMIN_STATUS_ACTIVE = 1;   // 管理员状态：正常
    const ADMIN_STATUS_DELETED = -1;   // 管理员状态：删除
}
