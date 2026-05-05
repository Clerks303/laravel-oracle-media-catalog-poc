<?php

namespace App\Rules;

use App\Models\Broadcast;
use App\Models\Program;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Two broadcasts on the same channel may not overlap in airtime.
 * A broadcast spans [scheduled_at, scheduled_at + program.duration_min).
 * Adjacency (A.end == B.start) is allowed.
 *
 * Attached to the `scheduled_at` attribute; uses the surrounding
 * program_id / channel_id from the request payload via DataAwareRule.
 */
class BroadcastNonOverlapping implements ValidationRule, DataAwareRule
{
    private array $data = [];

    public function __construct(private ?int $excludeId = null) {}

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $programId = $this->data['program_id'] ?? null;
        $channelId = $this->data['channel_id'] ?? null;

        // Other rules will surface missing/invalid values; nothing to check here.
        if (! $programId || ! $channelId || ! $value) {
            return;
        }

        $program = Program::find($programId);
        if (! $program) {
            return;
        }

        $start = Carbon::parse($value);
        $end   = $start->copy()->addMinutes((int) $program->duration_min);

        // Pre-filter to a sane window (max realistic program length is ~24h)
        // to avoid loading the full broadcast table for the channel.
        $existing = Broadcast::query()
            ->with('program:id,duration_min')
            ->where('channel_id', $channelId)
            ->when($this->excludeId, fn ($q) => $q->where('id', '!=', $this->excludeId))
            ->where('scheduled_at', '>=', $start->copy()->subDay())
            ->where('scheduled_at', '<',  $end->copy()->addDay())
            ->get();

        foreach ($existing as $b) {
            $bStart = Carbon::parse($b->scheduled_at);
            $bEnd   = $bStart->copy()->addMinutes((int) ($b->program->duration_min ?? 0));

            // Half-open intervals: [start, end). Adjacency is OK.
            if ($bStart->lt($end) && $start->lt($bEnd)) {
                $fail("This broadcast overlaps an existing one on the same channel (broadcast #{$b->id}).");
                return;
            }
        }
    }
}
