<?php

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Models\Channel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purge broadcasts whose airing window has long expired.
 *
 * A broadcast is considered "expired" once `replay_until` (or, when absent,
 * `scheduled_at + program.duration_min`) is older than the `--before` cutoff.
 * The default cutoff is 6 months ago.
 *
 * Hard delete: broadcasts intentionally do not use SoftDeletes (see DECISIONS §9).
 */
class BroadcastsPurge extends Command
{
    protected $signature = 'broadcasts:purge
        {--before= : Cutoff date (YYYY-MM-DD); defaults to 6 months ago}
        {--channel= : Limit purge to a single channel by code}
        {--dry-run : Report what would be deleted without touching the database}
        {--chunk=1000 : Batch size for delete iterations}';

    protected $description = 'Delete broadcasts whose replay window expired before the cutoff';

    public function handle(): int
    {
        $cutoff = $this->resolveCutoff();
        if ($cutoff === null) {
            return self::INVALID;
        }

        $channelId = null;
        if ($code = $this->option('channel')) {
            $channel = Channel::where('code', $code)->first();
            if (! $channel) {
                $this->error("Channel '{$code}' not found.");
                return self::INVALID;
            }
            $channelId = $channel->id;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(1, (int) $this->option('chunk'));

        $expiredIdsQuery = fn () => Broadcast::query()
            ->join('programs', 'programs.id', '=', 'broadcasts.program_id')
            ->whereRaw($this->expiryWhereClause(), [$cutoff, $cutoff])
            ->when($channelId, fn ($q) => $q->where('broadcasts.channel_id', $channelId));

        $total = $expiredIdsQuery()->count();
        $this->line(sprintf(
            '%s %d broadcast(s) expired before %s%s.',
            $dryRun ? 'Would delete' : 'Deleting',
            $total,
            $cutoff->toDateString(),
            $channelId ? " on channel {$this->option('channel')}" : '',
        ));

        if ($total === 0 || $dryRun) {
            $this->reportBreakdown($expiredIdsQuery());
            return self::SUCCESS;
        }

        $deleted = 0;
        $expiredIdsQuery()
            ->select('broadcasts.id')
            ->orderBy('broadcasts.id')
            ->chunkById($chunk, function ($rows) use (&$deleted) {
                $ids = $rows->pluck('id')->all();
                $deleted += Broadcast::whereIn('id', $ids)->delete();
            }, 'broadcasts.id', 'id');

        $this->info("Purged {$deleted} broadcast(s).");
        return self::SUCCESS;
    }

    private function resolveCutoff(): ?Carbon
    {
        $raw = $this->option('before');
        if (! $raw) {
            return now()->subMonths(6)->startOfDay();
        }
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            $this->error("Invalid --before date: {$raw}");
            return null;
        }
    }

    /**
     * Expiry test: replay_until < cutoff (when set), else scheduled_at + duration_min < cutoff.
     * The cutoff binding is reused twice — caller passes [$cutoff, $cutoff].
     *
     * NB: NUMTODSINTERVAL is Oracle-specific. The SQLite path uses datetime() instead.
     */
    private function expiryWhereClause(): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'oracle') {
            return '(broadcasts.replay_until IS NOT NULL AND broadcasts.replay_until < ?)
                 OR (broadcasts.replay_until IS NULL
                     AND broadcasts.scheduled_at + NUMTODSINTERVAL(programs.duration_min, \'MINUTE\') < ?)';
        }
        return "(broadcasts.replay_until IS NOT NULL AND broadcasts.replay_until < ?)
             OR (broadcasts.replay_until IS NULL
                 AND datetime(broadcasts.scheduled_at, '+' || programs.duration_min || ' minutes') < ?)";
    }

    private function reportBreakdown($query): void
    {
        $rows = $query
            ->select('broadcasts.channel_id')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('broadcasts.channel_id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $channels = Channel::whereIn('id', $rows->pluck('channel_id'))->pluck('code', 'id');
        $this->table(['channel', 'broadcasts'], $rows->map(fn ($r) => [
            $channels[$r->channel_id] ?? "#{$r->channel_id}",
            $r->c,
        ])->all());
    }
}
