<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** A recurring bill to send a client (e.g. yearly domain + hosting); reminds remind_days before next_due. */
class VentureSchedule extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected $casts = ['next_due' => 'date', 'items' => 'array', 'is_active' => 'boolean'];
}
