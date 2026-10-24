<?php

namespace App\Models\Concerns;

use App\Models\User;

/** Stamps user_id with the signed-in user when a row is created (unless already set). */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::creating(function ($model) {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
