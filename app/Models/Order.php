<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Order extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user_order';

    protected $fillable = [
        'order_listing_id',
        'display_order_id',
        'amount',
        'payment_method',
        'buy_user_id',
        'buy_account_name',
        'buy_account_number',
        'buy_bank_name',
        'buy_issue_bank_name',
        'sell_user_id',
        'sell_account_name',
        'sell_account_number',
        'sell_bank_name',
        'sell_issue_bank_name',
        'sell_qr_code',
        'exchange_rate',
        'total_price',
        'total_cny_price',
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
