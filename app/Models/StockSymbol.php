<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One DSE share: company, category (decides T+ settlement) and latest price. */
class StockSymbol extends Model
{
    protected $guarded = [];

    protected $casts = ['price' => 'float', 'prev_close' => 'float', 'price_date' => 'date'];
}
