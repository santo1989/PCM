<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCalculation extends Model
{
    use HasFactory;
    protected $guarded = []; // Allow mass assignment

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeFilterByMonthYear($query, $month, $year)
    {
        return $query->whereMonth('date', $month)->whereYear('date', $year);
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
