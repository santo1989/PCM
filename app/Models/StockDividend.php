<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** A declared dividend, received or still receivable. */
class StockDividend extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected $casts = ['record_date' => 'date', 'received_date' => 'date', 'gross' => 'float', 'tax' => 'float', 'net' => 'float'];
}
