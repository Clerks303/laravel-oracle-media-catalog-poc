<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    use HasFactory;

    protected $table = 'broadcasts';

    protected $fillable = ['program_id', 'channel_id', 'scheduled_at', 'replay_until'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'replay_until' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $q->where('scheduled_at', '>=', $from);
        }
        if ($to) {
            $q->where('scheduled_at', '<=', $to);
        }
        return $q;
    }
}
