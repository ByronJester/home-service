<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'description', 'discount', 'requirements', 'usage', 'image', 'is_active'])]
class Promo extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'discount' => 'integer',
            'requirements' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
