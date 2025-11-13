-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2025-11-02 07:36:23
-- 服务器版本： 5.7.44-log
-- PHP 版本： 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `jh_shop`
--

-- --------------------------------------------------------

--
-- 表的结构 `jh_admin`
--

CREATE TABLE `jh_admin` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'ID',
  `role` varchar(50) NOT NULL DEFAULT 'admin' COMMENT '角色唯一标识',
  `user_name` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `real_name` varchar(50) DEFAULT '' COMMENT '真实姓名',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码（哈希存储）',
  `email` varchar(100) DEFAULT '' COMMENT '电子邮箱',
  `phone` varchar(20) DEFAULT '' COMMENT '电话',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `last_login_ip` varchar(50) DEFAULT '' COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用',
  `two_factor_secret` varchar(255) DEFAULT '' COMMENT '二步验证密钥'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

--
-- 转存表中的数据 `jh_admin`
--

INSERT INTO `jh_admin` (`id`, `role`, `user_name`, `real_name`, `password`, `email`, `phone`, `avatar`, `last_login_ip`, `last_login_time`, `created_at`, `updated_at`, `status`, `two_factor_secret`) VALUES
(1, 'admin', 'admin', '管理员', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'admin@jh.com', '13800000001', '/avatars/admin.png', '', NULL, '2025-10-14 20:41:55', '2025-10-14 20:41:55', 1, ''),
(2, 'superAdmin', 'superAdmin', '超级管理员', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'superadmin@jh.com', '13800000002', '/avatars/superadmin.png', '', NULL, '2025-10-14 20:41:55', '2025-10-14 20:41:55', 1, '');

-- --------------------------------------------------------

--
-- 表的结构 `jh_admin_privilege_rule`
--

CREATE TABLE `jh_admin_privilege_rule` (
  `id` int(10) UNSIGNED NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '父ID，0为根节点',
  `router_key` varchar(50) DEFAULT NULL COMMENT '路径标识（唯一）',
  `type` enum('menu','action') NOT NULL DEFAULT 'action' COMMENT 'menu=菜单,action=操作',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名字',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `sort_order` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员权限规则表';

--
-- 转存表中的数据 `jh_admin_privilege_rule`
--

INSERT INTO `jh_admin_privilege_rule` (`id`, `pid`, `router_key`, `type`, `name`, `remark`, `created_at`, `updated_at`, `sort_order`, `status`) VALUES
(1, 0, 'index', 'menu', 'index', '系统首页', '2025-10-14 20:39:33', '2025-10-14 20:39:33', 1, 1),
(2, 0, 'dashboard', 'menu', 'dashboard', '仪表盘', '2025-10-14 20:39:33', '2025-10-14 20:39:33', 2, 1);

-- --------------------------------------------------------

--
-- 表的结构 `jh_admin_role`
--

CREATE TABLE `jh_admin_role` (
  `role` varchar(50) NOT NULL COMMENT '角色唯一标识',
  `name` varchar(50) NOT NULL COMMENT '角色名',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员角色表';

--
-- 转存表中的数据 `jh_admin_role`
--

INSERT INTO `jh_admin_role` (`role`, `name`, `remark`, `created_at`, `updated_at`, `status`) VALUES
('admin', '管理员', '管理员拥有部分权限', '2025-10-14 20:38:50', '2025-10-14 20:38:50', 1),
('superAdmin', '超级管理员', '超级管理员拥有全部权限', '2025-10-14 20:38:50', '2025-10-14 20:38:50', 1);

-- --------------------------------------------------------

--
-- 表的结构 `jh_admin_role_rule`
--

CREATE TABLE `jh_admin_role_rule` (
  `role` varchar(50) NOT NULL,
  `rule_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色与规则关联表';

--
-- 转存表中的数据 `jh_admin_role_rule`
--

INSERT INTO `jh_admin_role_rule` (`role`, `rule_id`) VALUES
('admin', 1),
('superAdmin', 1),
('admin', 2),
('superAdmin', 2);

-- --------------------------------------------------------

--
-- 表的结构 `jh_platform_config`
--

CREATE TABLE `jh_platform_config` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '配置ID',
  `payment_address` varchar(255) NOT NULL DEFAULT '' COMMENT '收款地址（USDT）',
  `payment_qr_code` varchar(255) DEFAULT NULL COMMENT '收款二维码路径（二维码图片）',
  `transfer_fee` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '转账手续费',
  `withdrawl_fee` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '提现手续费',
  `exchange_rate_platform` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '平台兑换比率',
  `exchange_rate_alipay` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '支付宝兑换比率',
  `exchange_rate_wechat` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '微信兑换比率',
  `exchange_rate_bank` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '银行卡兑换比率',
  `advertisement_text` varchar(1024) DEFAULT NULL COMMENT '平台广告语',
  `remote_order_config` json DEFAULT NULL COMMENT '远程下单扩展配置（JSON 格式）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台配置表';

--
-- 转存表中的数据 `jh_platform_config`
--

INSERT INTO `jh_platform_config` (`id`, `payment_address`, `payment_qr_code`, `transfer_fee`, `withdrawl_fee`, `exchange_rate_platform`, `exchange_rate_alipay`, `exchange_rate_wechat`, `exchange_rate_bank`, `advertisement_text`, `remote_order_config`, `created_at`, `updated_at`) VALUES
(1, 'jjusfafxsdfsjeexxseeed', '', '2.00', '2.00', '7.25', '7.24', '7.25', '7.26', 'Welcome to our platform!', '{\"openMarkets\": [\"alipay\", \"wechat\", \"bank\"], \"amountOptions\": [500, 1000, 2000]}', '2025-10-14 20:51:37', '2025-10-14 20:51:37');

-- --------------------------------------------------------

--
-- 表的结构 `jh_user`
--

CREATE TABLE `jh_user` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'ID',
  `inviter_id` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '上级ID',
  `inviter_name` varchar(255) NOT NULL DEFAULT '' COMMENT '上级名字',
  `root_agent_id` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '所属代理ID',
  `root_agent_name` varchar(255) NOT NULL DEFAULT '' COMMENT '所属代理名字',
  `role` varchar(50) NOT NULL DEFAULT 'default' COMMENT '角色唯一标识',
  `user_name` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `real_name` varchar(50) DEFAULT '' COMMENT '真实姓名',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码（哈希存储）',
  `email` varchar(100) DEFAULT '' COMMENT '电子邮箱',
  `phone` varchar(20) DEFAULT '' COMMENT '电话',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `last_login_ip` varchar(50) DEFAULT '' COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `invite_code` varchar(50) DEFAULT NULL COMMENT '邀请码',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用, -1删除',
  `two_factor_secret` varchar(255) DEFAULT '' COMMENT '二步验证密钥'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

--
-- 转存表中的数据 `jh_user`
--

INSERT INTO `jh_user` (`id`, `inviter_id`, `inviter_name`, `root_agent_id`, `root_agent_name`, `role`, `user_name`, `real_name`, `password`, `email`, `phone`, `avatar`, `last_login_ip`, `last_login_time`, `invite_code`, `created_at`, `updated_at`, `status`, `two_factor_secret`) VALUES
(1, 0, '', 0, '', 'platform', 'rootAgent', '平台总代理', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'platform@jh.com', '13800000001', '/avatars/admin.png', '127.0.0.1', '2025-10-14 20:46:59', '88888888', '2025-10-14 20:46:59', '2025-10-14 20:46:59', 1, ''),
(2, 1, 'rootAgent', 2, 'agent1', 'agent', 'agent1', '代理1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'agent1@jh.com', '13800000002', '/avatars/agent.png', '192.168.0.10', '2025-10-14 20:46:59', '88000001', '2025-10-14 20:46:59', '2025-10-14 20:46:59', 1, ''),
(3, 2, 'agent1', 2, 'agent1', 'seller', 'seller1', '卖家1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'seller1@jh.com', '13800000003', '/avatars/seller.png', '192.168.0.11', '2025-10-14 20:46:59', NULL, '2025-10-14 20:46:59', '2025-10-14 20:46:59', 1, ''),
(4, 3, 'seller1', 2, 'agent1', 'seller', 'seller2', '卖家2', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'seller2@jh.com', '13800000004', '/avatars/seller.png', '192.168.0.11', '2025-10-14 20:46:59', NULL, '2025-10-14 20:46:59', '2025-10-14 20:46:59', 1, ''),
(5, 3, 'seller1', 0, '', 'buyer', 'buyer1', '买家1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'buyer1@jh.com', '13800000005', '/avatars/buyer.png', '10.0.0.1', '2025-10-14 20:46:59', NULL, '2025-10-14 20:46:59', '2025-10-14 20:46:59', 1, ''),
(6, 0, '', 0, '', 'autoBuyer', 'autoBuyer1', '自动化买家1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'autobuyer@jh.com', '13800000006', '/avatars/buyer.png', '10.0.0.1', '2025-10-14 20:46:59', NULL, '2025-10-14 20:46:59', '2025-10-14 20:46:59', 1, '');

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_account`
--

CREATE TABLE `jh_user_account` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '账户ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `total_balance` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '总资产',
  `available_balance` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '可用资产',
  `payment_password` varchar(255) NOT NULL DEFAULT '' COMMENT '支付密码（哈希存储）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户账户表';

--
-- 转存表中的数据 `jh_user_account`
--

INSERT INTO `jh_user_account` (`id`, `user_id`, `total_balance`, `available_balance`, `payment_password`) VALUES
(1, 1, 0.00, 0.00, '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC'),
(2, 2, 0.00, 0.00, '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC'),
(3, 3, 0.00, 0.00, '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC'),
(4, 4, 0.00, 0.00, '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC'),
(5, 5, 0.00, 0.00, '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC'),
(6, 6, 0.00, 0.00, '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC');

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_daily_report`
--

CREATE TABLE `jh_user_daily_report` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_date` date NOT NULL COMMENT '统计日期',
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `type` enum('buyer','autoBuyer','agent') NOT NULL COMMENT '报表类型: buyer=买家, autoBuyer=自动化买家, agent=代理',
  `order_count` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '订单数量',
  `total_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '订单总额',
  `commission_rate` decimal(18,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '佣金比例',
  `commission_amount` decimal(18,2) NOT NULL DEFAULT '0.00' COMMENT '佣金数额',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户日报表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_financial_record`
--

CREATE TABLE `jh_user_financial_record` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '财务变动记录ID',
  `transaction_id` varchar(255) DEFAULT NULL COMMENT '交易ID（例如，支付流水号、转账流水号）',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `amount` decimal(15,2) NOT NULL COMMENT '变动金额（可以是负值或正值）',
  `exchange_rate` decimal(15,2) NOT NULL COMMENT '兑换比率',
  `cny_amount` decimal(15,2) NOT NULL COMMENT '等值的CNY提现金额',
  `fee` decimal(15,2) NOT NULL COMMENT '手续费',
  `actual_amount` decimal(15,2) NOT NULL COMMENT '实际变动金额（可以是负值或正值，加上了手续费）',
  `balance_before` decimal(15,2) NOT NULL COMMENT '变动前的账户余额',
  `balance_after` decimal(15,2) NOT NULL COMMENT '变动后的账户余额',
  `transaction_type` enum('recharge','transfer_send','transfer_receive','withdraw','order_buy','order_auto_buy','order_sell','order_auto_sell') NOT NULL COMMENT '变动类型：recharge（充值）、transfer_send（转账-转出）、transfer_receive（转账-转入）、withdraw（提现）、order（订单）',
  `reference_id` int(10) UNSIGNED DEFAULT NULL COMMENT '关联ID，比如转账的记录ID，订单ID',
  `display_reference_id` varchar(255) NOT NULL COMMENT '关联的展示ID, 用于展示的充值单号（比如202501010001）',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：-1 删除；0 进行中；1 已完结',
  `description` text COMMENT '变动描述（可选）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '变动时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '状态更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户财务变动记录表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_order`
--

CREATE TABLE `jh_user_order` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '订单ID',
  `order_listing_id` int(10) UNSIGNED NOT NULL COMMENT '挂单ID（外键关联 jh_user_order_listing 表）',
  `display_order_id` varchar(255) NOT NULL COMMENT '用于展示的订单号（比如20250101_0001）',
  `buy_transaction_id` varchar(255) DEFAULT NULL COMMENT '买家的交易ID（例如，支付流水号、转账流水号）',
  `sell_transaction_id` varchar(255) DEFAULT NULL COMMENT '卖家的交易ID（例如，支付流水号、转账流水号）',
  `type` enum('normal','auto') NOT NULL DEFAULT 'normal' COMMENT 'normal: 普通订单, auto: 自动化订单',
  `amount` decimal(15,2) UNSIGNED NOT NULL COMMENT '购买数量',
  `payment_method` enum('bank','alipay','wechat') NOT NULL COMMENT '支付方式',
  `buy_user_id` int(10) UNSIGNED NOT NULL COMMENT '购买用户ID（外键关联 jh_user 表）',
  `buy_account_name` varchar(255) NOT NULL COMMENT '购买账户名/持卡人',
  `buy_account_number` varchar(255) NOT NULL COMMENT '购买账户号码/卡号',
  `buy_bank_name` varchar(255) DEFAULT NULL COMMENT '购买银行名',
  `buy_issue_bank_name` varchar(255) DEFAULT NULL COMMENT '购买开户行名',
  `sell_user_id` int(10) UNSIGNED NOT NULL COMMENT '售卖用户ID（外键关联 jh_user 表）',
  `sell_account_name` varchar(255) NOT NULL COMMENT '售卖账户名/持卡人',
  `sell_account_number` varchar(255) NOT NULL COMMENT '售卖账户号码/卡号',
  `sell_bank_name` varchar(255) DEFAULT NULL COMMENT '售卖银行名',
  `sell_issue_bank_name` varchar(255) DEFAULT NULL COMMENT '售卖开户行名',
  `sell_qr_code` varchar(512) DEFAULT NULL COMMENT '二维码图片链接（仅适用于支付宝、微信）',
  `exchange_rate` decimal(15,2) UNSIGNED NOT NULL COMMENT '兑换比率（币价）',
  `total_price` decimal(15,2) UNSIGNED NOT NULL COMMENT '购买总金额',
  `total_cny_price` decimal(15,2) UNSIGNED NOT NULL COMMENT '购买总金额（人民币）',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单状态：-1 已删除; 0 买家未支付; 1 已支付待卖家确认; 2 卖家已确认；3 超时取消； 4 争议；5 ；争议通过；6 争议撤单',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '订单创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '订单更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户订单表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_order_listing`
--

CREATE TABLE `jh_user_order_listing` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '挂单ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `amount` decimal(15,2) NOT NULL COMMENT '总数量',
  `remain_amount` decimal(15,2) NOT NULL COMMENT '剩余数量',
  `min_sale_amount` decimal(15,2) UNSIGNED NOT NULL COMMENT '最低售卖数量，低于此数量下架挂单',
  `payment_method` enum('bank','alipay','wechat') DEFAULT NULL COMMENT '支付方式',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '挂单状态：0 已下架，1 在售 2 禁售 3 锁库存冻结 4 售完 5 已撤单',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户挂单表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_payment_method`
--

CREATE TABLE `jh_user_payment_method` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '支付方式ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `payment_method` enum('bank','alipay','wechat') NOT NULL COMMENT '支付方式类型：银行卡、支付宝、Paypal',
  `account_name` varchar(255) NOT NULL COMMENT '账户名/持卡人',
  `account_number` varchar(255) NOT NULL COMMENT '账户号码/卡号',
  `bank_name` varchar(255) DEFAULT NULL COMMENT '银行名',
  `issue_bank_name` varchar(255) DEFAULT NULL COMMENT '开户行名',
  `qr_code` varchar(512) DEFAULT NULL COMMENT '二维码图片链接（仅适用于支付宝、微信）',
  `sort_order` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `default_payment` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认支付方式',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用, -1删除',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户支付方式表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_privilege_rule`
--

CREATE TABLE `jh_user_privilege_rule` (
  `id` int(10) UNSIGNED NOT NULL,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '父ID，0为根节点',
  `router_key` varchar(50) DEFAULT NULL COMMENT '路径标识（唯一）',
  `type` enum('menu','action') NOT NULL DEFAULT 'action' COMMENT 'menu=菜单,action=操作',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名字',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `sort_order` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限规则表';

--
-- 转存表中的数据 `jh_user_privilege_rule`
--

INSERT INTO `jh_user_privilege_rule` (`id`, `pid`, `router_key`, `type`, `name`, `remark`, `created_at`, `updated_at`, `sort_order`, `status`) VALUES
(1, 0, 'index', 'menu', 'index', '系统首页', '2025-10-14 20:44:55', '2025-10-14 20:44:55', 1, 1),
(2, 0, 'profile', 'menu', 'profile', '用户信息', '2025-10-14 20:44:55', '2025-10-14 20:44:55', 2, 1),
(3, 0, 'setting', 'menu', 'setting', '设置', '2025-10-14 20:44:55', '2025-10-14 20:44:55', 3, 1);

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_recharge`
--

CREATE TABLE `jh_user_recharge` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '充值记录ID',
  `display_recharge_id` varchar(255) NOT NULL COMMENT '用于展示的充值单号（比如202501010001）',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键，关联用户表）',
  `user_name` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `amount` decimal(15,2) NOT NULL COMMENT '充值金额',
  `exchange_rate` decimal(15,2) NOT NULL COMMENT '兑换比率',
  `cny_amount` decimal(15,2) NOT NULL COMMENT '等值的CNY充值金额',
  `actual_amount` decimal(15,2) NOT NULL COMMENT '实际变动金额（充值金额）',
  `recharge_address` varchar(512) NOT NULL COMMENT '充值地址(USDT-TRC20)',
  `recharge_images` text NOT NULL COMMENT '充值截图',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '交易状态：0 待确认; -1 已驳回; 1 已通过',
  `balance_before` decimal(15,2) NOT NULL COMMENT '变动前的账户余额',
  `balance_after` decimal(15,2) NOT NULL COMMENT '变动后的账户余额',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '充值请求时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '状态更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户充值记录表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_role`
--

CREATE TABLE `jh_user_role` (
  `role` varchar(50) NOT NULL COMMENT '角色唯一标识',
  `name` varchar(50) NOT NULL COMMENT '角色名',
  `remark` varchar(50) NOT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0禁用，1启用'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表';

--
-- 转存表中的数据 `jh_user_role`
--

INSERT INTO `jh_user_role` (`role`, `name`, `remark`, `created_at`, `updated_at`, `status`) VALUES
('agent', '代理', '代理', '2025-10-14 20:43:59', '2025-10-14 20:43:59', 1),
('autoBuyer', '自动化买家', '自动化买家', '2025-10-14 20:43:59', '2025-10-14 20:43:59', 1),
('buyer', '买家', '买家', '2025-10-14 20:43:59', '2025-10-14 20:43:59', 1),
('default', '默认角色', '默认角色', '2025-10-14 20:43:59', '2025-10-14 20:43:59', 1),
('platform', '平台总代理', '平台总代理', '2025-10-14 20:43:59', '2025-10-14 20:43:59', 1),
('seller', '商户', '商户', '2025-10-14 20:43:59', '2025-10-14 20:43:59', 1);

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_role_rule`
--

CREATE TABLE `jh_user_role_rule` (
  `role` varchar(50) NOT NULL,
  `rule_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色与规则关联表';

--
-- 转存表中的数据 `jh_user_role_rule`
--

INSERT INTO `jh_user_role_rule` (`role`, `rule_id`) VALUES
('agent', 1),
('autoBuyer', 1),
('buyer', 1),
('seller', 1),
('agent', 2),
('autoBuyer', 2),
('buyer', 2),
('seller', 2),
('agent', 3),
('autoBuyer', 3),
('buyer', 3),
('seller', 3);

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_transfer`
--

CREATE TABLE `jh_user_transfer` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '转账记录ID',
  `sender_transaction_id` varchar(255) DEFAULT NULL COMMENT '发送者的交易ID（例如，支付流水号、转账流水号）',
  `receiver_transaction_id` varchar(255) DEFAULT NULL COMMENT '接收者的交易ID（例如，支付流水号、转账流水号）',
  `display_transfer_id` varchar(255) NOT NULL COMMENT '用于展示的充值单号（比如202501010001）',
  `sender_user_id` int(10) UNSIGNED NOT NULL COMMENT '发送者用户ID（外键，关联用户表）',
  `receiver_user_id` int(10) UNSIGNED NOT NULL COMMENT '接收者用户ID（外键，关联用户表）',
  `sender_user_name` varchar(32) NOT NULL COMMENT '发送者用户名',
  `receiver_user_name` varchar(32) NOT NULL COMMENT '接收者用户名',
  `amount` decimal(15,2) NOT NULL COMMENT '转账金额',
  `exchange_rate` decimal(15,2) NOT NULL COMMENT '兑换比率',
  `cny_amount` decimal(15,2) NOT NULL COMMENT '等值的CNY转账金额',
  `fee` decimal(15,2) NOT NULL COMMENT '转账手续费',
  `actual_amount` decimal(15,2) NOT NULL COMMENT '实际变动金额（转账金额+手续费）',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '交易状态：0 待确认; -1 已驳回; 1 已通过',
  `sender_balance_before` decimal(15,2) NOT NULL COMMENT '变动前的发送者账户余额',
  `sender_balance_after` decimal(15,2) NOT NULL COMMENT '变动后的发送者账户余额',
  `receiver_balance_before` decimal(15,2) NOT NULL COMMENT '变动前的接收者账户余额',
  `receiver_balance_after` decimal(15,2) NOT NULL COMMENT '变动后的接收者账户余额',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '转账请求时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '转账状态更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户转账记录表';

-- --------------------------------------------------------

--
-- 表的结构 `jh_user_withdrawal`
--

CREATE TABLE `jh_user_withdrawal` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '提现记录ID',
  `display_withdraw_id` varchar(255) NOT NULL COMMENT '用于展示的充值单号（比如202501010001）',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键，关联用户表）',
  `user_name` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `amount` decimal(15,2) NOT NULL COMMENT '提现金额',
  `exchange_rate` decimal(15,2) NOT NULL COMMENT '兑换比率',
  `cny_amount` decimal(15,2) NOT NULL COMMENT '等值的CNY提现金额',
  `fee` decimal(15,2) NOT NULL COMMENT '提现手续费',
  `actual_amount` decimal(15,2) NOT NULL COMMENT '实际变动金额（提现金额+手续费）',
  `withdraw_address` varchar(512) NOT NULL COMMENT '提现地址(USDT-TRC20)',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '交易状态：0 待确认; -1 已驳回; 1 已通过',
  `balance_before` decimal(15,2) NOT NULL COMMENT '变动前的账户余额',
  `balance_after` decimal(15,2) NOT NULL COMMENT '变动后的账户余额',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提现申请时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '状态更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户提现记录表';

--
-- 转储表的索引
--

--
-- 表的索引 `jh_admin`
--
ALTER TABLE `jh_admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_name` (`user_name`),
  ADD KEY `fk_admin_admin_role` (`role`);

--
-- 表的索引 `jh_admin_privilege_rule`
--
ALTER TABLE `jh_admin_privilege_rule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_router_key` (`router_key`);

--
-- 表的索引 `jh_admin_role`
--
ALTER TABLE `jh_admin_role`
  ADD PRIMARY KEY (`role`);

--
-- 表的索引 `jh_admin_role_rule`
--
ALTER TABLE `jh_admin_role_rule`
  ADD PRIMARY KEY (`role`,`rule_id`),
  ADD KEY `idx_rule_id` (`rule_id`);

--
-- 表的索引 `jh_platform_config`
--
ALTER TABLE `jh_platform_config`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `jh_user`
--
ALTER TABLE `jh_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_name` (`user_name`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD UNIQUE KEY `uk_invite_code` (`invite_code`),
  ADD KEY `fk_user_role` (`role`);

--
-- 表的索引 `jh_user_account`
--
ALTER TABLE `jh_user_account`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_account_user_id` (`user_id`);

--
-- 表的索引 `jh_user_daily_report`
--
ALTER TABLE `jh_user_daily_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_date` (`report_date`,`user_id`,`type`);

--
-- 表的索引 `jh_user_financial_record`
--
ALTER TABLE `jh_user_financial_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_financial_record_user_id` (`user_id`);

--
-- 表的索引 `jh_user_order`
--
ALTER TABLE `jh_user_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_orders_order_listing_id` (`order_listing_id`),
  ADD KEY `fk_user_orders_buy_user_id` (`buy_user_id`),
  ADD KEY `fk_user_orders_sell_user_id` (`sell_user_id`);

--
-- 表的索引 `jh_user_order_listing`
--
ALTER TABLE `jh_user_order_listing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_listing_user_id` (`user_id`);

--
-- 表的索引 `jh_user_payment_method`
--
ALTER TABLE `jh_user_payment_method`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payment_method_user_id` (`user_id`);

--
-- 表的索引 `jh_user_privilege_rule`
--
ALTER TABLE `jh_user_privilege_rule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_router_key` (`router_key`);

--
-- 表的索引 `jh_user_recharge`
--
ALTER TABLE `jh_user_recharge`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_recharge_user_id` (`user_id`);

--
-- 表的索引 `jh_user_role`
--
ALTER TABLE `jh_user_role`
  ADD PRIMARY KEY (`role`);

--
-- 表的索引 `jh_user_role_rule`
--
ALTER TABLE `jh_user_role_rule`
  ADD PRIMARY KEY (`role`,`rule_id`),
  ADD KEY `idx_rule_id` (`rule_id`);

--
-- 表的索引 `jh_user_transfer`
--
ALTER TABLE `jh_user_transfer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_transfer_sender_user_id` (`sender_user_id`),
  ADD KEY `fk_user_transfer_receiver_user_id` (`receiver_user_id`);

--
-- 表的索引 `jh_user_withdrawal`
--
ALTER TABLE `jh_user_withdrawal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_withdrawal_user_id` (`user_id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `jh_admin`
--
ALTER TABLE `jh_admin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID', AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `jh_admin_privilege_rule`
--
ALTER TABLE `jh_admin_privilege_rule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `jh_platform_config`
--
ALTER TABLE `jh_platform_config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `jh_user`
--
ALTER TABLE `jh_user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID', AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `jh_user_account`
--
ALTER TABLE `jh_user_account`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '账户ID';

--
-- 使用表AUTO_INCREMENT `jh_user_daily_report`
--
ALTER TABLE `jh_user_daily_report`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `jh_user_financial_record`
--
ALTER TABLE `jh_user_financial_record`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '财务变动记录ID';

--
-- 使用表AUTO_INCREMENT `jh_user_order`
--
ALTER TABLE `jh_user_order`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '订单ID';

--
-- 使用表AUTO_INCREMENT `jh_user_order_listing`
--
ALTER TABLE `jh_user_order_listing`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '挂单ID';

--
-- 使用表AUTO_INCREMENT `jh_user_payment_method`
--
ALTER TABLE `jh_user_payment_method`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '支付方式ID';

--
-- 使用表AUTO_INCREMENT `jh_user_privilege_rule`
--
ALTER TABLE `jh_user_privilege_rule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `jh_user_recharge`
--
ALTER TABLE `jh_user_recharge`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '充值记录ID';

--
-- 使用表AUTO_INCREMENT `jh_user_transfer`
--
ALTER TABLE `jh_user_transfer`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '转账记录ID';

--
-- 使用表AUTO_INCREMENT `jh_user_withdrawal`
--
ALTER TABLE `jh_user_withdrawal`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '提现记录ID';

--
-- 限制导出的表
--

--
-- 限制表 `jh_admin`
--
ALTER TABLE `jh_admin`
  ADD CONSTRAINT `fk_admin_admin_role` FOREIGN KEY (`role`) REFERENCES `jh_admin_role` (`role`);

--
-- 限制表 `jh_admin_role_rule`
--
ALTER TABLE `jh_admin_role_rule`
  ADD CONSTRAINT `fk_admin_role` FOREIGN KEY (`role`) REFERENCES `jh_admin_role` (`role`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_rule` FOREIGN KEY (`rule_id`) REFERENCES `jh_admin_privilege_rule` (`id`) ON DELETE CASCADE;

--
-- 限制表 `jh_user`
--
ALTER TABLE `jh_user`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role`) REFERENCES `jh_user_role` (`role`);

--
-- 限制表 `jh_user_account`
--
ALTER TABLE `jh_user_account`
  ADD CONSTRAINT `fk_user_account_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user` (`id`);

--
-- 限制表 `jh_user_financial_record`
--
ALTER TABLE `jh_user_financial_record`
  ADD CONSTRAINT `fk_user_financial_record_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user` (`id`);

--
-- 限制表 `jh_user_order`
--
ALTER TABLE `jh_user_order`
  ADD CONSTRAINT `fk_user_orders_buy_user_id` FOREIGN KEY (`buy_user_id`) REFERENCES `jh_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_orders_order_listing_id` FOREIGN KEY (`order_listing_id`) REFERENCES `jh_user_order_listing` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_orders_sell_user_id` FOREIGN KEY (`sell_user_id`) REFERENCES `jh_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `jh_user_order_listing`
--
ALTER TABLE `jh_user_order_listing`
  ADD CONSTRAINT `fk_order_listing_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user` (`id`);

--
-- 限制表 `jh_user_payment_method`
--
ALTER TABLE `jh_user_payment_method`
  ADD CONSTRAINT `fk_payment_method_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user` (`id`);

--
-- 限制表 `jh_user_recharge`
--
ALTER TABLE `jh_user_recharge`
  ADD CONSTRAINT `fk_user_recharge_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user` (`id`);

--
-- 限制表 `jh_user_role_rule`
--
ALTER TABLE `jh_user_role_rule`
  ADD CONSTRAINT `fk_role` FOREIGN KEY (`role`) REFERENCES `jh_user_role` (`role`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rule` FOREIGN KEY (`rule_id`) REFERENCES `jh_user_privilege_rule` (`id`) ON DELETE CASCADE;

--
-- 限制表 `jh_user_transfer`
--
ALTER TABLE `jh_user_transfer`
  ADD CONSTRAINT `fk_user_transfer_receiver_user_id` FOREIGN KEY (`receiver_user_id`) REFERENCES `jh_user` (`id`),
  ADD CONSTRAINT `fk_user_transfer_sender_user_id` FOREIGN KEY (`sender_user_id`) REFERENCES `jh_user` (`id`);

--
-- 限制表 `jh_user_withdrawal`
--
ALTER TABLE `jh_user_withdrawal`
  ADD CONSTRAINT `fk_user_withdrawal_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
