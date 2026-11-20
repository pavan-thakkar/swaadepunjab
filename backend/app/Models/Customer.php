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
        'first_order_at' => 'datetime',
    ];

    protected static function booted()
    {
        // One row per phone number. Only real orders count towards orders / spent
        // (cancelled and unpaid online orders are ignored); counter walk-ins without a number are left out.
        static::addGlobalScope('unique_customers', function (Builder $builder) {
            $real = "status NOT IN ('cancelled','awaiting_payment')";
            $builder->select(
                DB::raw('MAX(id) as id'),
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('MAX(customer_email) as customer_email'),
                'customer_phone',
                DB::raw('MAX(city) as city'),
                DB::raw('MAX(delivery_address) as delivery_address'),
                DB::raw("SUM(CASE WHEN {$real} THEN 1 ELSE 0 END) as total_orders"),
                DB::raw("SUM(CASE WHEN {$real} THEN total - COALESCE(refund_amount, 0) ELSE 0 END) as total_spent"),
                DB::raw('MIN(created_at) as first_order_at'),
                DB::raw('MAX(created_at) as last_order_at')
            )
                ->whereNotNull('customer_phone')
                ->whereNotIn('customer_phone', ['', 'Counter'])
                ->groupBy('customer_phone');
        });
    }

    /** Fresh numbers for one phone (the list row only knows its own grouped values). */
    public static function summary(string $phone): array
    {
        $orders = Order::with('items')->where('customer_phone', $phone)->orderByDesc('created_at')->get();
        $real = $orders->whereNotIn('status', ['cancelled', 'awaiting_payment']);
        $spent = (float) $real->sum(fn ($o) => (float) $o->total - (float) ($o->refund_amount ?? 0));

        // Favourite dishes
        $fav = OrderItem::whereIn('order_id', $real->pluck('id'))
            ->selectRaw('name, SUM(quantity) as qty')->groupBy('name')->orderByDesc('qty')->limit(5)->get();

        return [
            'orders'     => $orders,
            'count'      => $real->count(),
            'cancelled'  => $orders->where('status', 'cancelled')->count(),
            'spent'      => $spent,
            'avg'        => $real->count() ? $spent / $real->count() : 0,
            'first'      => $orders->last()?->created_at,
            'last'       => $orders->first()?->created_at,
            'favourites' => $fav,
            'points'     => class_exists(\App\Services\Loyalty::class) ? (int) \App\Services\Loyalty::balance($phone) : 0,
        ];
    }

    // Relationship to get all orders for this customer (by phone)
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_phone', 'customer_phone');
    }
}
