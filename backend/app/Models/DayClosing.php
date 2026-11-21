<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One saved night hisab (cash-up) per business day. */
class DayClosing extends Model
{
    protected $fillable = ['business_date', 'opening_cash', 'expenses', 'expected_cash', 'counted_cash', 'cash_difference',
        'upi_counted', 'card_counted', 'totals', 'note', 'closed_by', 'closed_by_name'];

    protected $casts = ['business_date' => 'date', 'totals' => 'array', 'opening_cash' => 'decimal:2', 'expenses' => 'decimal:2',
        'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2', 'cash_difference' => 'decimal:2', 'upi_counted' => 'decimal:2', 'card_counted' => 'decimal:2'];
}
