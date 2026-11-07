<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/** An invoice, bill or cash memo for a freelance / business client; paid by ledger income rows (invoice_id). */
class VentureInvoice extends Model
{
    use BelongsToUser;

    public const TYPES = ['Invoice', 'Bill', 'Cash memo'];

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'due_date' => 'date', 'items' => 'array', 'total' => 'float'];

    public function payments()
    {
        return $this->hasMany(ExpenseCalculation::class, 'invoice_id');
    }
}
