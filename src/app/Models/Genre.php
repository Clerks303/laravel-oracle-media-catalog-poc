<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $table = 'genres';

    protected $fillable = ['code', 'label'];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_genre')->withTimestamps();
    }
}
