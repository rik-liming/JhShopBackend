<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PlatformConfig extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_platform_config';

    protected $fillable = [
        'payment_address',
        'payment_qr_code',
        'transfer_fee',
        'withdrawl_fee',
        'exchange_rate_platform',
        'exchange_rate_alipay',
        'exchange_rate_wechat',
        'exchange_rate_bank',
        'advertisement_text',
        'remote_order_config',
    ];

    protected $hidden = [
    ];

    protected $casts = [
        'remote_order_config' => 'array', // 自动将 JSON 转为数组
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
