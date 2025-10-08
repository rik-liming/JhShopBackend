<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AdminPrivilegeRule extends Model
{
    protected $table = 'jh_admin_privilege_rule';

    protected $fillable = [
        'pid', 
        'router_key', 
        'type', 
        'name', 
        'remark',
        'sort_order', 
        'status'
    ];

    /**
     * 指定日期字段
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * 全局序列化时间格式
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(\DateTimeInterface $date)
    {
        return Carbon::instance($date)->format('Y-m-d H:i:s');
    }
}
