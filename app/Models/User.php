<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user';

    protected $fillable = [
        'inviter_id',
        'inviter_name',
        'root_agent_id',
        'root_agent_name',
        'role',
        'user_name',
        'real_name',
        'password',
        'email',
        'phone',
        'avatar',
        'last_login_ip',
        'last_login_time',
        'invite_code',
        'has_commission',
        'status',
        'two_factor_secret',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role_id');
    }

    /**
     * 指定日期字段
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'last_login_time',
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
