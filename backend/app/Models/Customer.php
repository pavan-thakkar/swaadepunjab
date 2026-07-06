<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    // Binds to the orders table to aggregate customer profiles
    protected $table = 'orders';

    public $incrementing = false;

    protected $casts = [
        'total_orders'  => 'integer',
        'total_spent'   => 'float',
        'last_order_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('unique_customers', function (Builder $builder) {
            $builder->select(
                DB::raw('MAX(id) as id'),
                'customer_name',
                'customer_email',
                'customer_phone',
                'city',
                'delivery_address',
                DB::raw('COUNT(id) as total_orders'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('MAX(created_at) as last_order_at')
            )->groupBy('customer_phone');
        });
    }

    // Relationship to get all orders for this customer (by phone)
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_phone', 'customer_phone');
    }
}
