<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FinancialRecord extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user_financial_record';

    protected $fillable = [
        'transaction_id',
        'user_id',
        'amount',
        'exchange_rate',
        'cny_amount',
        'actual_amount',
        'fee',
        'balance_before',
        'balance_after',
        'transaction_type',
        'order_id',
        'payment_method',
        'description',
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
