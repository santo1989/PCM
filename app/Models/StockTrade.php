<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** One buy or sell on the DSE BO account (portfolio maths: App\Services\StockPortfolio). */
class StockTrade extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'qty' => 'integer', 'rate' => 'float', 'commission' => 'float'];
}
