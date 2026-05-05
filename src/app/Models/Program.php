<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'programs';

    protected $fillable = ['channel_id', 'title', 'synopsis', 'duration_min'];

    protected $casts = [
        'duration_min' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'program_genre')->withTimestamps();
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }
        // Oracle is case-sensitive by default; UPPER both sides
        $needle = '%' . mb_strtoupper($term) . '%';
        return $q->whereRaw('UPPER(title) LIKE ?', [$needle]);
    }

    public function scopeOfGenre(Builder $q, ?string $genreCode): Builder
    {
        if (! $genreCode) {
            return $q;
        }
        return $q->whereHas('genres', fn ($g) => $g->where('code', $genreCode));
    }
}
