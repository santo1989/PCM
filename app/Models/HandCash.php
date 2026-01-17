<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HandCash extends Model
{
    use HasFactory;
    protected $guarded = []; // Allow mass assignment

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
