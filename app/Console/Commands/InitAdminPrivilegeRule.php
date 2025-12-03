<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AdminPrivilegeRule;
use App\Models\AdminRoleRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 用于快速生成管理员权限规则
 */
class InitAdminPrivilegeRule extends Command
{
    /**
     * 命令的名字（执行时用这个）
     */
    protected $signature = 'initAdminPrivilegeRules';

    /**
     * 命令描述
     */
    protected $description = '初始化管理员权限规则表（jh_admin_privilege_rule）';

    /**
     * 执行命令逻辑
     */
    public function handle()
    {
        $rules = [
            [
                'pid' => 0,
                'router_key' => '/setting',
                'type' => 'menu',
                'name' => '常规管理',
                'remark' => '常规管理',
                'sort_order' => 1,
                'status' => 1,
            ],
            [
                'pid' => 1,
                'router_key' => '/setting/system',
                'type' => 'menu',
                'name' => '系统配置',
                'remark' => '系统配置',
                'sort_order' => 2,
                'status' => 1,
            ],
            [
                'pid' => 2,
                'router_key' => '/setting/system:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 3,
                'status' => 1,
            ],
            [
                'pid' => 2,
                'router_key' => '/setting/system:modify',
                'type' => 'action',
                'name' => '修改',
                'remark' => '修改',
                'sort_order' => 4,
                'status' => 1,
            ],
            [
                'pid' => 1,
                'router_key' => '/setting/person',
                'type' => 'menu',
                'name' => '个人资料',
                'remark' => '个人资料',
                'sort_order' => 5,
                'status' => 1,
            ],
            [
                'pid' => 5,
                'router_key' => '/setting/person:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 6,
                'status' => 1,
            ],
            [
                'pid' => 5,
                'router_key' => '/setting/person:modify',
                'type' => 'action',
                'name' => '修改',
                'remark' => '修改',
                'sort_order' => 7,
                'status' => 1,
            ],
            [
                'pid' => 0,
                'router_key' => '/permission',
                'type' => 'menu',
                'name' => '权限管理',
                'remark' => '权限管理',
                'sort_order' => 8,
                'status' => 1,
            ],
            [
                'pid' => 8,
                'router_key' => '/permission/role',
                'type' => 'menu',
                'name' => '角色管理',
                'remark' => '角色管理',
                'sort_order' => 9,
                'status' => 1,
            ],
            [
                'pid' => 9,
                'router_key' => '/permission/role:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 10,
                'status' => 1,
            ],
            [
                'pid' => 9,
                'router_key' => '/permission/role:add',
                'type' => 'action',
                'name' => '添加',
                'remark' => '添加',
                'sort_order' => 11,
                'status' => 1,
            ],
            [
                'pid' => 9,
                'router_key' => '/permission/role:privilegeManage',
                'type' => 'action',
                'name' => '权限管理',
                'remark' => '权限管理',
                'sort_order' => 12,
                'status' => 1,
            ],
            [
                'pid' => 9,
                'router_key' => '/permission/role:ban',
                'type' => 'action',
                'name' => '启用/封禁',
                'remark' => '启用/封禁',
                'sort_order' => 13,
                'status' => 1,
            ],
            [
                'pid' => 8,
                'router_key' => '/permission/admin',
                'type' => 'menu',
                'name' => '管理员管理',
                'remark' => '管理员管理',
                'sort_order' => 14,
                'status' => 1,
            ],
            [
                'pid' => 14,
                'router_key' => '/permission/admin:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 15,
                'status' => 1,
            ],
            [
                'pid' => 14,
                'router_key' => '/permission/admin:add',
                'type' => 'action',
                'name' => '添加',
                'remark' => '添加',
                'sort_order' => 16,
                'status' => 1,
            ],
            [
                'pid' => 14,
                'router_key' => '/permission/admin:ban',
                'type' => 'action',
                'name' => '启用/封禁',
                'remark' => '启用/封禁',
                'sort_order' => 17,
                'status' => 1,
            ],
            [
                'pid' => 14,
                'router_key' => '/permission/admin:modifyPassword',
                'type' => 'action',
                'name' => '修改密码',
                'remark' => '修改密码',
                'sort_order' => 18,
                'status' => 1,
            ],
            [
                'pid' => 0,
                'router_key' => '/user',
                'type' => 'menu',
                'name' => '会员管理',
                'remark' => '会员管理',
                'sort_order' => 19,
                'status' => 1,
            ],
            [
                'pid' => 19,
                'router_key' => '/user/index',
                'type' => 'menu',
                'name' => '会员管理',
                'remark' => '会员管理',
                'sort_order' => 20,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 21,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:grantNormalRole',
                'type' => 'action',
                'name' => '分配普通角色（买家/商户）',
                'remark' => '分配普通角色（买家/商户）',
                'sort_order' => 22,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:grantSpecialRole',
                'type' => 'action',
                'name' => '分配特别角色（代理/自动化买家）',
                'remark' => '分配特别角色（代理/自动化买家）',
                'sort_order' => 23,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:scanInviterRelation',
                'type' => 'action',
                'name' => '查看下级',
                'remark' => '查看下级',
                'sort_order' => 24,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:scanAccount',
                'type' => 'action',
                'name' => '查看资产',
                'remark' => '查看资产',
                'sort_order' => 25,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:modifyAccount',
                'type' => 'menu',
                'name' => '修改资产',
                'remark' => '修改资产',
                'sort_order' => 26,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:modifyPassword',
                'type' => 'menu',
                'name' => '修改密码',
                'remark' => '修改密码',
                'sort_order' => 27,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:ban',
                'type' => 'action',
                'name' => '启用/封禁',
                'remark' => '启用/封禁',
                'sort_order' => 28,
                'status' => 1,
            ],
            [
                'pid' => 0,
                'router_key' => '/transaction',
                'type' => 'menu',
                'name' => '财务管理',
                'remark' => '财务管理',
                'sort_order' => 29,
                'status' => 1,
            ],
            [
                'pid' => 29,
                'router_key' => '/transaction/recharge',
                'type' => 'menu',
                'name' => '充值管理',
                'remark' => '充值管理',
                'sort_order' => 30,
                'status' => 1,
            ],
            [
                'pid' => 30,
                'router_key' => '/transaction/recharge:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 31,
                'status' => 1,
            ],
            [
                'pid' => 30,
                'router_key' => '/transaction/recharge:approve',
                'type' => 'action',
                'name' => '审批（通过/驳回）',
                'remark' => '审批（通过/驳回）',
                'sort_order' => 32,
                'status' => 1,
            ],
            [
                'pid' => 29,
                'router_key' => '/transaction/transfer',
                'type' => 'menu',
                'name' => '转账管理',
                'remark' => '转账管理',
                'sort_order' => 33,
                'status' => 1,
            ],
            [
                'pid' => 33,
                'router_key' => '/transaction/transfer:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 34,
                'status' => 1,
            ],
            [
                'pid' => 33,
                'router_key' => '/transaction/transfer:approve',
                'type' => 'action',
                'name' => '审批（通过/驳回）',
                'remark' => '审批（通过/驳回）',
                'sort_order' => 35,
                'status' => 1,
            ],
            [
                'pid' => 29,
                'router_key' => '/transaction/withdraw',
                'type' => 'menu',
                'name' => '提现管理',
                'remark' => '提现管理',
                'sort_order' => 36,
                'status' => 1,
            ],
            [
                'pid' => 36,
                'router_key' => '/transaction/withdraw:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 37,
                'status' => 1,
            ],
            [
                'pid' => 36,
                'router_key' => '/transaction/withdraw:approve',
                'type' => 'action',
                'name' => '审批（通过/驳回）',
                'remark' => '审批（通过/驳回）',
                'sort_order' => 38,
                'status' => 1,
            ],
            [
                'pid' => 0,
                'router_key' => '/order',
                'type' => 'menu',
                'name' => '订单管理',
                'remark' => '订单管理',
                'sort_order' => 39,
                'status' => 1,
            ],
            [
                'pid' => 39,
                'router_key' => '/order/listing',
                'type' => 'menu',
                'name' => '挂单管理',
                'remark' => '挂单管理',
                'sort_order' => 40,
                'status' => 1,
            ],
            [
                'pid' => 40,
                'router_key' => '/order/listing:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 41,
                'status' => 1,
            ],
            [
                'pid' => 40,
                'router_key' => '/order/listing:approve',
                'type' => 'action',
                'name' => '审批（上架/下架/禁售）',
                'remark' => '审批（上架/下架/禁售）',
                'sort_order' => 42,
                'status' => 1,
            ],
            [
                'pid' => 39,
                'router_key' => '/order/normal',
                'type' => 'menu',
                'name' => '常规订单',
                'remark' => '常规订单',
                'sort_order' => 43,
                'status' => 1,
            ],
            [
                'pid' => 43,
                'router_key' => '/order/normal:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 44,
                'status' => 1,
            ],
            [
                'pid' => 43,
                'router_key' => '/order/normal:approve',
                'type' => 'action',
                'name' => '审批（争议处理）',
                'remark' => '审批（争议处理）',
                'sort_order' => 45,
                'status' => 1,
            ],
            [
                'pid' => 39,
                'router_key' => '/order/auto',
                'type' => 'menu',
                'name' => '自动化订单',
                'remark' => '自动化订单',
                'sort_order' => 46,
                'status' => 1,
            ],
            [
                'pid' => 46,
                'router_key' => '/order/auto:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 47,
                'status' => 1,
            ],
            [
                'pid' => 46,
                'router_key' => '/order/auto:approve',
                'type' => 'action',
                'name' => '审批（争议处理）',
                'remark' => '审批（争议处理）',
                'sort_order' => 48,
                'status' => 1,
            ],
            [
                'pid' => 0,
                'router_key' => '/report',
                'type' => 'menu',
                'name' => '报表管理',
                'remark' => '报表管理',
                'sort_order' => 49,
                'status' => 1,
            ],
            [
                'pid' => 49,
                'router_key' => '/report/agent',
                'type' => 'menu',
                'name' => '代理',
                'remark' => '代理',
                'sort_order' => 50,
                'status' => 1,
            ],
            [
                'pid' => 50,
                'router_key' => '/report/agent:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 51,
                'status' => 1,
            ],
            [
                'pid' => 49,
                'router_key' => '/report/auto_buyer',
                'type' => 'menu',
                'name' => '自动化买家',
                'remark' => '自动化买家',
                'sort_order' => 52,
                'status' => 1,
            ],
            [
                'pid' => 52,
                'router_key' => '/report/auto_buyer:scan',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 53,
                'status' => 1,
            ],
            [
                'pid' => 49,
                'router_key' => '/report/buyer',
                'type' => 'menu',
                'name' => '系统买家',
                'remark' => '系统买家',
                'sort_order' => 54,
                'status' => 1,
            ],
            [
                'pid' => 54,
                'router_key' => '/report/buyer:select',
                'type' => 'action',
                'name' => '查看',
                'remark' => '查看',
                'sort_order' => 55,
                'status' => 1,
            ],
            [
                'pid' => 20,
                'router_key' => '/user/index:switchCommission',
                'type' => 'action',
                'name' => '开启/关闭佣金',
                'remark' => '开启/关闭佣金',
                'sort_order' => 56,
                'status' => 1,
            ],
        ];

        // 如果数据存在则清空
        if (AdminPrivilegeRule::count() > 0) {
            if ($this->confirm('表中已有数据，是否清空后再初始化？')) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                AdminRoleRule::truncate();
                AdminPrivilegeRule::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->info('已清空原有数据。');
            } else {
                $this->warn('已取消初始化。');
                return Command::SUCCESS;
            }
        }

        // 批量插入（一次性插入全部）
        AdminPrivilegeRule::insert($rules);

        $this->info('✅ 插入完成，共 ' . count($rules) . ' 条记录。');

        return Command::SUCCESS;
    }
}
