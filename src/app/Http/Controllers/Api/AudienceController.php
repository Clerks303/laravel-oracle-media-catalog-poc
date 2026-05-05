<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Calls PL/SQL pipelined functions to return audience aggregates,
 * demonstrating the "ability to work without an ORM" point in the ARTE brief.
 */
class AudienceController extends Controller
{
    public function perChannel(Request $request)
    {
        $from = $request->query('from', now()->subMonth()->toDateString());
        $to   = $request->query('to', now()->toDateString());

        $rows = DB::select(
            'SELECT channel_code, broadcast_count, avg_duration
             FROM TABLE(audience_stats_pkg.per_channel(:p_from, :p_to))',
            ['p_from' => $from, 'p_to' => $to]
        );

        return response()->json([
            'data'   => $rows,
            'window' => ['from' => $from, 'to' => $to],
            'source' => 'pl/sql function audience_stats_pkg.per_channel',
        ]);
    }

    /**
     * Top N programs by airtime (broadcast_count × duration_min) over a window,
     * optionally filtered by channel code. Backed by audience_stats_pkg.top_programs.
     */
    public function topPrograms(Request $request)
    {
        $validated = $request->validate([
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date', 'after_or_equal:from'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'channel' => ['nullable', 'string', 'max:16'],
        ]);

        $from    = $validated['from']    ?? now()->subMonth()->toDateString();
        $to      = $validated['to']      ?? now()->toDateString();
        $limit   = (int) ($validated['limit'] ?? 10);
        $channel = $validated['channel'] ?? null;

        $rows = DB::select(
            'SELECT program_id, title, channel_code, broadcast_count, total_airtime_min
             FROM TABLE(audience_stats_pkg.top_programs(:p_from, :p_to, :p_limit, :p_channel))',
            [
                'p_from'    => $from,
                'p_to'      => $to,
                'p_limit'   => $limit,
                'p_channel' => $channel,
            ]
        );

        return response()->json([
            'data'    => $rows,
            'window'  => ['from' => $from, 'to' => $to],
            'limit'   => $limit,
            'channel' => $channel,
            'source'  => 'pl/sql function audience_stats_pkg.top_programs',
        ]);
    }
}
