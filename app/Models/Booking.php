<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'detailed_address', 'contact_number', 'body_parts', 'design_picture', 'service_date', 'price_range', 'status'])]
class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'price_range' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
