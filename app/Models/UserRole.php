<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class UserRole extends Model
{
    protected $table = 'jh_user_role';

    protected $fillable = [
        'key', 
        'name', 
        'remark', 
        'status'
    ];

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(
            UserPrivilegeRule::class,
            'jh_user_role_rule',
            'role',
            'rule_id'
        );
    }

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
