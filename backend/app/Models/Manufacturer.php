<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Manufacturer extends Model
{
    use HasFactory;

    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
    }
}