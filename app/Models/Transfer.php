<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\FinancialRecord;

class Transfer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user_transfer';

    protected $fillable = [
        'sender_user_id',
        'sender_user_name',
        'receiver_user_id',
        'receiver_user_name',
        'amount',
        'exchange_rate',
        'cny_amount',
        'fee',      
        'actual_amount',
        'sender_balance_before',
        'sender_balance_after',
        'receiver_balance_before',
        'receiver_balance_after',
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
