<?php

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'oracle') {
        $this->markTestSkipped('audience_stats_pkg.top_programs is Oracle-only PL/SQL.');
    }
});

it('returns top programs ordered by airtime desc', function () {
    $channel = Channel::factory()->create(['code' => 'TST1']);

    // Long Show: 2 broadcasts × 90 min = 180 airtime
    $long = Program::factory()->create([
        'channel_id'   => $channel->id,
        'title'        => 'Long Show',
        'duration_min' => 90,
    ]);
    // Short Show: 5 broadcasts × 30 min = 150 airtime
    $short = Program::factory()->create([
        'channel_id'   => $channel->id,
        'title'        => 'Short Show',
        'duration_min' => 30,
    ]);

    // Space broadcasts so the anti-overlap trigger doesn't trip.
    $base = now()->subDays(5)->startOfHour();
    $slot = 0;
    foreach (range(1, 2) as $_) {
        Broadcast::factory()->create([
            'program_id'   => $long->id,
            'channel_id'   => $channel->id,
            'scheduled_at' => $base->copy()->addHours(6 * $slot++),
        ]);
    }
    foreach (range(1, 5) as $_) {
        Broadcast::factory()->create([
            'program_id'   => $short->id,
            'channel_id'   => $channel->id,
            'scheduled_at' => $base->copy()->addHours(6 * $slot++),
        ]);
    }

    $this->getJson('/api/v1/audience/top-programs?limit=10')
        ->assertOk()
        ->assertJsonPath('source', 'pl/sql function audience_stats_pkg.top_programs')
        ->assertJsonPath('limit', 10)
        ->assertJsonPath('data.0.title', 'Long Show')
        ->assertJsonPath('data.0.broadcast_count', '2')
        ->assertJsonPath('data.0.total_airtime_min', '180')
        ->assertJsonPath('data.1.title', 'Short Show')
        ->assertJsonPath('data.1.total_airtime_min', '150');
});

it('respects the limit parameter', function () {
    $channel = Channel::factory()->create();

    $base = now()->subDays(5)->startOfHour();
    $slot = 0;
    Program::factory()->count(5)->create(['channel_id' => $channel->id])
        ->each(function (Program $p) use ($channel, $base, &$slot) {
            Broadcast::factory()->create([
                'program_id'   => $p->id,
                'channel_id'   => $channel->id,
                'scheduled_at' => $base->copy()->addHours(6 * $slot++),
            ]);
        });

    $this->getJson('/api/v1/audience/top-programs?limit=3')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('filters by channel code', function () {
    $kept    = Channel::factory()->create(['code' => 'KEPT']);
    $dropped = Channel::factory()->create(['code' => 'DROP']);

    foreach ([$kept, $dropped] as $c) {
        $p = Program::factory()->create(['channel_id' => $c->id]);
        Broadcast::factory()->create([
            'program_id'   => $p->id,
            'channel_id'   => $c->id,
            'scheduled_at' => now()->subDays(5),
        ]);
    }

    $this->getJson('/api/v1/audience/top-programs?channel=KEPT')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.channel_code', 'KEPT');
});

// Validation cases (limit out-of-range, to before from, malformed dates,
// channel too long) live in AudienceValidationTest — they don't need an
// Oracle service container to run.
