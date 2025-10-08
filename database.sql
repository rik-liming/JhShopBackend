SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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


