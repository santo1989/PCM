<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HandCash extends Model
{
    use HasFactory, BelongsToUser;
    protected $guarded = []; // Allow mass assignment

    /** Fields shared by linked rows; changing one side updates the other. */
    public const SHARED_FIELDS = ['name', 'date', 'amount'];

    protected static function booted()
    {
        // Query-builder updates don't fire model events, so this can't loop.
        static::updated(function (HandCash $cash) {
            $changes = array_intersect_key($cash->getChanges(), array_flip(self::SHARED_FIELDS));
            if (!$changes) {
                return;
            }
            if ($cash->expense_calculation_id) {
                ExpenseCalculation::whereKey($cash->expense_calculation_id)->update($changes);
            }
            if ($cash->transfer_pair_id) {
                static::whereKey($cash->transfer_pair_id)->update($changes);
            }
        });
    }

    public function expenseCalculation()
    {
        return $this->belongsTo(ExpenseCalculation::class);
    }

    public function transferPair()
    {
        return $this->belongsTo(HandCash::class, 'transfer_pair_id');
    }

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    /**
     * Ensure 'types' is always stored uppercase.
     */
    public function setTypesAttribute($value)
    {
        $this->attributes['types'] = is_null($value) ? null : strtoupper($value);
    }

    /**
     * Ensure 'rules' is always stored uppercase.
     */
    public function setRulesAttribute($value)
    {
        $this->attributes['rules'] = is_null($value) ? null : strtoupper($value);
    }
}
