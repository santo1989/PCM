<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** A BO account deposit, withdrawal, fee or dividend receipt. */
class StockCash extends Model
{
    use BelongsToUser;

    protected $table = 'stock_cash';

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'cash_in' => 'float', 'cash_out' => 'float'];
}
