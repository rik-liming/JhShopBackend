SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for jh_admin_role
-- ----------------------------
CREATE TABLE `jh_admin_role` (
  `role` varchar(50) NOT NULL COMMENT '角色唯一标识',
  `name` varchar(50) NOT NULL COMMENT '角色名',
  `remark` varchar(50) NOT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  PRIMARY KEY (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='管理员角色表';


-- ----------------------------
-- Records of jh_admin_role
-- ----------------------------
INSERT INTO `jh_admin_role` (`role`, `name`, `remark`, `status`) VALUES
('admin', '管理员', '管理员', 1),
('superAdmin', '超级管理员', '超级管理员', 1)


-- ----------------------------
-- Table structure for jh_admin_privilege_rule
-- ----------------------------
CREATE TABLE `jh_admin_privilege_rule` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父ID，0为根节点',
  `router_key` varchar(50) DEFAULT NULL COMMENT '路径标识（唯一）',
  `type` enum('menu','action') NOT NULL DEFAULT 'action' COMMENT 'menu=菜单,action=操作',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名字',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `sort_order` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_router_key` (`router_key`)
) ENGINE=InnoDB AUTO_INCREMENT = 3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='管理员权限规则表';


-- ----------------------------
-- Records of jh_admin_privilege_rule
-- ----------------------------
-- 一级菜单
INSERT INTO `jh_admin_privilege_rule` (`id`, `pid`, `router_key`, `type`, `name`, `remark`, `sort_order`, `status`) VALUES
(1, 0, 'index', 'menu', 'index', '系统首页', 1, 1),
(2, 0, 'dashboard', 'menu', 'dashboard', '仪表盘', 2, 1)


-- ----------------------------
-- Table structure for jh_admin_role_rule
-- ----------------------------
CREATE TABLE `jh_admin_role_rule` (
  `role` varchar(50) NOT NULL,
  `rule_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`role`,`rule_id`),
  KEY `idx_rule_id` (`rule_id`),
  CONSTRAINT `fk_admin_role` FOREIGN KEY (`role`) REFERENCES `jh_admin_role`(`role`) ON DELETE CASCADE,
  CONSTRAINT `fk_admin_rule` FOREIGN KEY (`rule_id`) REFERENCES `jh_admin_privilege_rule`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='角色与规则关联表';


-- ----------------------------
-- Records of jh_admin_role_rule
-- ----------------------------
-- 管理员
INSERT INTO `jh_admin_role_rule` (`role`, `rule_id`)
SELECT 'admin', id FROM jh_admin_privilege_rule;

-- 超级管理员
INSERT INTO `jh_admin_role_rule` (`role`, `rule_id`)
SELECT 'superAdmin', id FROM jh_admin_privilege_rule;

-- ----------------------------
-- Table structure for jh_admin
-- ----------------------------
DROP TABLE IF EXISTS `jh_admin`;
CREATE TABLE `jh_admin` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `role` varchar(50) NOT NULL DEFAULT 'admin' COMMENT '角色唯一标识',
  `user_name` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `real_name` varchar(50) DEFAULT '' COMMENT '真实姓名',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码（哈希存储）',
  `email` varchar(100) DEFAULT '' COMMENT '电子邮箱',
  `phone` varchar(20) DEFAULT '' COMMENT '电话',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `last_login_ip` varchar(50) DEFAULT '' COMMENT '最后登录IP',
  `last_login_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  `two_factor_secret` varchar(255) DEFAULT '' COMMENT '二步验证密钥',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_user_name` (`user_name`),
  CONSTRAINT `fk_admin_admin_role` FOREIGN KEY (`role`) REFERENCES `jh_admin_role`(`role`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='管理员表';


-- ----------------------------
-- Records of jh_admin
-- ----------------------------
INSERT INTO `jh_admin` 
(`id`, `role`, `user_name`, `real_name`, `password`, `email`, `phone`, `avatar`, `last_login_ip`, `last_login_time`, `status`, `two_factor_secret`) 
VALUES
(1, 'admin', 'admin', '管理员', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'admin@jh.com', '13800000001', '/avatars/admin.png', '', NULL, 1, ''),
(2, 'superAdmin', 'superAdmin', '超级管理员', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'superadmin@jh.com', '13800000002', '/avatars/superadmin.png', '', NULL, 1, '')


-- ----------------------------
-- Table structure for jh_user_role
-- ----------------------------
CREATE TABLE `jh_user_role` (
  `role` varchar(50) NOT NULL COMMENT '角色唯一标识',
  `name` varchar(50) NOT NULL COMMENT '角色名',
  `remark` varchar(50) NOT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  PRIMARY KEY (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='角色表';


-- ----------------------------
-- Records of jh_user_role
-- ----------------------------
INSERT INTO `jh_user_role` (`role`, `name`, `remark`, `status`) VALUES
('default', '默认角色', '默认角色', 1),
('platform', '平台总代理', '平台总代理', 1),
('agent', '代理', '代理', 1),
('seller', '商户', '商户', 1),
('buyer', '买家', '买家', 1),
('autoBuyer', '自动化买家', '自动化买家', 1);


-- ----------------------------
-- Table structure for jh_user_privilege_rule
-- ----------------------------
CREATE TABLE `jh_user_privilege_rule` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父ID，0为根节点',
  `router_key` varchar(50) DEFAULT NULL COMMENT '路径标识（唯一）',
  `type` enum('menu','action') NOT NULL DEFAULT 'action' COMMENT 'menu=菜单,action=操作',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名字',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `sort_order` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_router_key` (`router_key`)
) ENGINE=InnoDB AUTO_INCREMENT = 4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='权限规则表';


-- ----------------------------
-- Records of jh_user_privilege_rule
-- ----------------------------
-- 一级菜单
INSERT INTO `jh_user_privilege_rule` (`id`, `pid`, `router_key`, `type`, `name`, `remark`, `sort_order`, `status`) VALUES
(1, 0, 'index', 'menu', 'index', '系统首页', 1, 1),
(2, 0, 'profile', 'menu', 'profile', '用户信息', 2, 1),
(3, 0, 'setting', 'menu', 'setting', '设置', 3, 1);


-- ----------------------------
-- Table structure for jh_user_role_rule
-- ----------------------------
CREATE TABLE `jh_user_role_rule` (
  `role` varchar(50) NOT NULL,
  `rule_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`role`,`rule_id`),
  KEY `idx_rule_id` (`rule_id`),
  CONSTRAINT `fk_role` FOREIGN KEY (`role`) REFERENCES `jh_user_role`(`role`) ON DELETE CASCADE,
  CONSTRAINT `fk_rule` FOREIGN KEY (`rule_id`) REFERENCES `jh_user_privilege_rule`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='角色与规则关联表';


-- ----------------------------
-- Records of jh_user_role_rule
-- ----------------------------
-- 代理
INSERT INTO `jh_user_role_rule` (`role`, `rule_id`)
SELECT 'agent', id FROM jh_user_privilege_rule;

-- 商家
INSERT INTO `jh_user_role_rule` (`role`, `rule_id`)
SELECT 'seller', id FROM jh_user_privilege_rule;

-- 买家
INSERT INTO `jh_user_role_rule` (`role`, `rule_id`)
SELECT 'buyer', id FROM jh_user_privilege_rule;

-- 自动化买家
INSERT INTO `jh_user_role_rule` (`role`, `rule_id`)
SELECT 'autoBuyer', id FROM jh_user_privilege_rule;

-- ----------------------------
-- Table structure for jh_user
-- ----------------------------
DROP TABLE IF EXISTS `jh_user`;
CREATE TABLE `jh_user` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `inviter_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上级ID',
  `inviter_name` varchar(255) NOT NULL DEFAULT '' COMMENT '上级名字',
  `root_agent_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属代理ID',
  `root_agent_name` varchar(255) NOT NULL DEFAULT '' COMMENT '所属代理名字',
  `role` varchar(50) NOT NULL DEFAULT 'default' COMMENT '角色唯一标识',
  `user_name` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `real_name` varchar(50) DEFAULT '' COMMENT '真实姓名',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码（哈希存储）',
  `email` varchar(100) DEFAULT '' COMMENT '电子邮箱',
  `phone` varchar(20) DEFAULT '' COMMENT '电话',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `last_login_ip` varchar(50) DEFAULT '' COMMENT '最后登录IP',
  `last_login_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `invite_code` varchar(50) DEFAULT NULL COMMENT '邀请码',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用, -1删除',
  `two_factor_secret` varchar(255) DEFAULT '' COMMENT '二步验证密钥',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_user_name` (`user_name`),
  UNIQUE KEY `uk_invite_code` (`invite_code`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role`) REFERENCES `jh_user_role`(`role`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户表';


-- ----------------------------
-- Records of jh_user
-- ----------------------------
INSERT INTO `jh_user` 
(`id`, `inviter_id`, `inviter_name`, `root_agent_id`, `root_agent_name`, `role`, `user_name`, `real_name`, `password`, `email`, `phone`, `avatar`, `last_login_ip`, `last_login_time`, `invite_code`, `status`, `two_factor_secret`) 
VALUES
(1, 0, '', 0, '', 'platform', 'rootAgent', '平台总代理', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'platform@jh.com', '13800000001', '/avatars/admin.png', '127.0.0.1', NOW(), '88888888', 1, ''),
(2, 1, 'rootAgent', 2, 'agent1', 'agent', 'agent1', '代理1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'agent1@jh.com', '13800000002', '/avatars/agent.png', '192.168.0.10', NOW(), '88000001', 1, ''),
(3, 2, 'agent1', 2, 'agent1', 'seller', 'seller1', '卖家1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'seller1@jh.com', '13800000003', '/avatars/seller.png', '192.168.0.11', NOW(), '66000001', 1, ''),
(4, 3, 'seller1', 2, 'agent1', 'seller', 'seller2', '卖家2', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'seller2@jh.com', '13800000004', '/avatars/seller.png', '192.168.0.11', NOW(), '66000002', 1, ''),
(5, 3, 'seller1', 0, '', 'buyer', 'buyer1', '买家1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'buyer1@jh.com', '13800000005', '/avatars/buyer.png', '10.0.0.1', NOW(), NULL, 1, ''),
(6, 0, '', 0, '', 'autoBuyer', 'autoBuyer1', '自动化买家1', '$2y$10$7N/zQ0VifAsI9ruJsRe9P.AZYZizUDddAwBv9cNGw7w0ABiyMXEDC', 'autobuyer@jh.com', '13800000006', '/avatars/buyer.png', '10.0.0.1', NOW(), NULL, 1, '');


-- ----------------------------
-- Table structure for jh_user_account
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_account`;
CREATE TABLE `jh_user_account` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '账户ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `total_balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '总资产',
  `available_balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '可用资产',
  `payment_password` varchar(255) NOT NULL DEFAULT '' COMMENT '支付密码（哈希存储）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_account_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户账户表';

-- ----------------------------
-- Table structure for jh_user_payment_method
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_payment_method`;
CREATE TABLE `jh_user_payment_method` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '支付方式ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `pay_type` enum('bank', 'alipay', 'wechat') NOT NULL COMMENT '支付方式类型：银行卡、支付宝、Paypal',
  `account_name` varchar(255) NOT NULL COMMENT '账户名/持卡人',
  `account_number` varchar(255) NOT NULL COMMENT '账户号码/卡号',
  `bank_name` varchar(255) DEFAULT NULL COMMENT '银行名',
  `issue_bank_name` varchar(255) DEFAULT NULL COMMENT '开户行名',
  `qr_code` varchar(512) DEFAULT NULL COMMENT '二维码图片链接（仅适用于支付宝、微信）',
  `sort_order` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `default_payment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认支付方式',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_payment_method_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户支付方式表';


-- ----------------------------
-- Table structure for jh_user_order_listing
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_order_listing`;
CREATE TABLE `jh_user_order_listing` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '挂单ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `amount` decimal(15,2) NOT NULL COMMENT '总数量',
  `remain_amount` decimal(15,2) NOT NULL COMMENT '剩余数量',
  `min_sale_amount` decimal(15,2) UNSIGNED NOT NULL COMMENT '最低售卖数量，低于此数量下架挂单',
  `payment_method` enum('bank', 'alipay', 'wechat') DEFAULT NULL COMMENT '支付方式',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '挂单状态：0 已下架，1 在售',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_order_listing_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户挂单表';


-- ----------------------------
-- Table structure for jh_user_orders
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_orders`;
CREATE TABLE `jh_user_orders` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `order_listing_id` int(10) UNSIGNED NOT NULL COMMENT '挂单ID（外键关联 jh_user_order_listing 表）',
  `amount` decimal(15,2) UNSIGNED NOT NULL COMMENT '购买数量',
  `payment_method` enum('bank', 'alipay', 'wechat') NOT NULL COMMENT '支付方式',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '订单状态：0 买家未支付; 1 已支付待卖家确认; 2 卖家已确认；3 完成; -1 超时卖家未确认;',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '订单创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '订单更新时间',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`),
  CONSTRAINT `fk_user_orders_order_listing_id` FOREIGN KEY (`order_listing_id`) REFERENCES `jh_user_order_listing`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户订单表';


-- ----------------------------
-- Table structure for jh_user_recharge
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_recharge`;
CREATE TABLE `jh_user_recharge` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '充值记录ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键，关联用户表）',
  `amount` decimal(15,2) NOT NULL COMMENT '充值金额',
  `recharge_address` varchar(512) NOT NULL COMMENT '充值地址(USDT-TRC20)',
  `transaction_id` varchar(255) NOT NULL COMMENT '支付交易号',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '交易状态：0 待确认; -1 已驳回; 1 已通过',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '充值请求时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '状态更新时间',
  `description` text DEFAULT NULL COMMENT '备注（如支付相关备注）',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_recharge_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户充值记录表';


-- ----------------------------
-- Table structure for jh_user_transfer
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_transfer`;
CREATE TABLE `jh_user_transfer` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '转账记录ID',
  `sender_user_id` int(10) UNSIGNED NOT NULL COMMENT '发送者用户ID（外键，关联用户表）',
  `receiver_user_id` int(10) UNSIGNED NOT NULL COMMENT '接收者用户ID（外键，关联用户表）',
  `sender_user_name` varchar(32) NOT NULL COMMENT '发送者用户名',
  `receiver_user_name` varchar(32) NOT NULL COMMENT '接收者用户名',
  `amount` decimal(15,2) NOT NULL COMMENT '转账金额',
  `actual_amount` decimal(15,2) NOT NULL COMMENT '实际转账金额',
  `fee` decimal(15,2) NOT NULL COMMENT '转账手续费',
  `transaction_id` varchar(255) NOT NULL COMMENT '支付交易号',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '交易状态：0 待确认; -1 已驳回; 1 已通过',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '转账请求时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '转账状态更新时间',
  `description` text DEFAULT NULL COMMENT '备注（例如转账附言）',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_transfer_sender_user_id` FOREIGN KEY (`sender_user_id`) REFERENCES `jh_user`(`id`),
  CONSTRAINT `fk_user_transfer_receiver_user_id` FOREIGN KEY (`receiver_user_id`) REFERENCES `jh_user`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户转账记录表';


-- ----------------------------
-- Table structure for jh_user_withdrawal
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_withdrawal`;
CREATE TABLE `jh_user_withdrawal` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '提现记录ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键，关联用户表）',
  `amount` decimal(15,2) NOT NULL COMMENT '提现金额',
  `actual_amount` decimal(15,2) NOT NULL COMMENT '实际提现金额',
  `fee` decimal(15,2) NOT NULL COMMENT '提现手续费',
  `transaction_id` varchar(255) DEFAULT NULL COMMENT '提现交易号（用于支付平台跟踪）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '交易状态：0 待确认; -1 已驳回; 1 已通过',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提现申请时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '状态更新时间',
  `description` text DEFAULT NULL COMMENT '备注（如提现相关备注）',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_withdrawal_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户提现记录表';


-- ----------------------------
-- Table structure for jh_user_financial_record
-- ----------------------------
DROP TABLE IF EXISTS `jh_user_financial_record`;

CREATE TABLE `jh_user_financial_record` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '财务变动记录ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID（外键关联 jh_user 表）',
  `amount` decimal(15,2) NOT NULL COMMENT '变动金额（可以是负值或正值）',
  `balance_after` decimal(15,2) NOT NULL COMMENT '变动后的账户余额',
  `transaction_type` enum('income', 'expense', 'recharge', 'transfer', 'order_income', 'order_expense') NOT NULL COMMENT '变动类型：income（收入）、expense（支出）、recharge（充值）、transfer（转账）、order_income（订单收入）、order_expense（订单支出）',
  `order_id` int(10) UNSIGNED DEFAULT NULL COMMENT '订单ID（外键关联 jh_user_orders 表），如果是订单相关的财务变动，存储订单ID',
  `payment_method` enum('bank', 'alipay', 'wechat') DEFAULT NULL COMMENT '支付方式（仅用于订单相关的变动）',
  `transaction_id` varchar(255) DEFAULT NULL COMMENT '交易ID（例如，支付流水号、转账流水号）',
  `description` text DEFAULT NULL COMMENT '变动描述（可选）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '变动时间',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_user_financial_record_user_id` FOREIGN KEY (`user_id`) REFERENCES `jh_user`(`id`),
  CONSTRAINT `fk_user_financial_record_order_id` FOREIGN KEY (`order_id`) REFERENCES `jh_user_orders`(`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='用户财务变动记录表';


-- ----------------------------
-- Table structure for jh_platform_config
-- ----------------------------
DROP TABLE IF EXISTS `jh_platform_config`;
CREATE TABLE `jh_platform_config` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `payment_address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '收款地址（USDT）',
  `payment_qr_code` VARCHAR(255) DEFAULT NULL COMMENT '收款二维码路径（二维码图片）',
  `transfer_fee` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '转账手续费',
  `withdrawl_fee` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '提现手续费',
  `exchange_rate_alipay` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '支付宝兑换比率',
  `exchange_rate_wechat` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '微信兑换比率',
  `exchange_rate_bank` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '银行卡兑换比率',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='平台配置表';
