<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'ean', 'title', 'platform_id', 'cover', 'data',
    'release_date', 'developer', 'genres', 'status', 'play_status',
    'condition', 'edition_id', 'notes', 'rating', 'price_paid',
    'purchase_place', 'purchase_date', 'manual_status', 'region', 'age_rating',
])]
class Game extends Model
{
    use HasFactory;

    protected $casts = [
        'genres' => 'array',
        'release_date' => 'date',
        'purchase_date' => 'date',
        'price_paid' => 'decimal:2',
        'condition' => 'string', // Ya no es relación, es enum
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

}