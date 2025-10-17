<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Recharge extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user_recharge';

    protected $fillable = [
        'display_recharge_id',
        'user_id',
        'user_name',
        'amount',
        'exchange_rate',
        'cny_amount',
        'recharge_address',
        'recharge_images',
        'balance_before',
        'balance_after',
        'status',
    ];

    protected $hidden = [
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
