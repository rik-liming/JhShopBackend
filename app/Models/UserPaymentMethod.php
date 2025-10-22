<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserPaymentMethod extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user_payment_method';

    protected $fillable = [
        'user_id',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'issue_bank_name',
        'qr_code',
        'sort_order',
        'default_payment',
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
