<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, BelongsToUser;

    protected $guarded = []; // Allow mass assignment

    protected static function booted()
    {
        // Entries keep a copy of their category's types/rules; refresh them when those change.
        static::updated(function (Category $category) {
            $changes = array_intersect_key($category->getChanges(), array_flip(['types', 'rules']));
            if ($changes) {
                ExpenseCalculation::where('category_id', $category->id)->update($changes);
            }
        });
    }

    public function expenseCalculations()
    {
        return $this->hasMany(ExpenseCalculation::class);
    }

    /**
     * Ensure name is stored uppercase.
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value === null ? null : strtoupper($value);
    }

    /**
     * Ensure types is stored uppercase.
     */
    public function setTypesAttribute($value)
    {
        $this->attributes['types'] = $value === null ? null : strtoupper($value);
    }

    /**
     * Ensure rules is stored uppercase.
     */
    public function setRulesAttribute($value)
    {
        $this->attributes['rules'] = $value === null ? null : strtoupper($value);
    }

   
}
