<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetiCash extends Model
{
    use HasFactory;

    protected $guarded = []; // Allow mass assignment

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

    /**
     * Ensure name is stored uppercase.
     */
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value === null ? null : strtoupper($value);
    }
}
