<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\Order;

class OrderListing extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'jh_user_order_listing';

    protected $fillable = [
        'user_id',
        'amount',
        'remain_amount',
        'min_sale_amount',
        'payment_method',
        'status',
    ];

    protected $hidden = [
    ];

    /**
     * 一个挂单对应多个订单
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'order_listing_id', 'id');
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
