<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectedExpense extends Model
{
    use HasFactory, BelongsToUser;
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
