<?php

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes broadcasts whose airing window expired before the cutoff', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    // Old: scheduled 2 years ago, no replay → expired.
    $old = Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->subYears(2),
        'replay_until' => null,
    ]);
    // Recent: scheduled yesterday → not expired.
    $recent = Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->subDay(),
        'replay_until' => null,
    ]);

    $this->artisan('broadcasts:purge')
        ->assertSuccessful();

    expect(Broadcast::find($old->id))->toBeNull();
    expect(Broadcast::find($recent->id))->not->toBeNull();
});

it('keeps broadcasts whose replay_until is still in the future relative to cutoff', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    // Old broadcast but replay_until just expired — cutoff is 6 months ago.
    $b = Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->subYear(),
        'replay_until' => now()->subDays(10),
    ]);

    $this->artisan('broadcasts:purge')->assertSuccessful();

    expect(Broadcast::find($b->id))->not->toBeNull();
});

it('honors --dry-run by leaving the database untouched', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->subYears(2),
        'replay_until' => null,
    ]);

    $this->artisan('broadcasts:purge', ['--dry-run' => true])
        ->expectsOutputToContain('Would delete 1 broadcast(s)')
        ->assertSuccessful();

    expect(Broadcast::count())->toBe(1);
});

it('respects --before to override the default cutoff', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    // 2 months old: not expired by default (6mo), but expired with --before=now-1mo.
    $b = Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->subMonths(2),
        'replay_until' => null,
    ]);

    $this->artisan('broadcasts:purge', ['--before' => now()->subMonth()->toDateString()])
        ->assertSuccessful();

    expect(Broadcast::find($b->id))->toBeNull();
});

it('scopes to a single channel via --channel', function () {
    $kept    = Channel::factory()->create(['code' => 'KEPT']);
    $purged  = Channel::factory()->create(['code' => 'GONE']);
    $pKept   = Program::factory()->create(['channel_id' => $kept->id,   'duration_min' => 60]);
    $pPurged = Program::factory()->create(['channel_id' => $purged->id, 'duration_min' => 60]);

    $bKept = Broadcast::factory()->create([
        'program_id'   => $pKept->id,
        'channel_id'   => $kept->id,
        'scheduled_at' => now()->subYears(2),
        'replay_until' => null,
    ]);
    $bPurged = Broadcast::factory()->create([
        'program_id'   => $pPurged->id,
        'channel_id'   => $purged->id,
        'scheduled_at' => now()->subYears(2),
        'replay_until' => null,
    ]);

    $this->artisan('broadcasts:purge', ['--channel' => 'GONE'])
        ->assertSuccessful();

    expect(Broadcast::find($bKept->id))->not->toBeNull();
    expect(Broadcast::find($bPurged->id))->toBeNull();
});

it('rejects an unknown --channel code', function () {
    $this->artisan('broadcasts:purge', ['--channel' => 'NOPE'])
        ->expectsOutputToContain("Channel 'NOPE' not found.")
        ->assertExitCode(2);
});

it('rejects an invalid --before date', function () {
    $this->artisan('broadcasts:purge', ['--before' => 'not-a-date'])
        ->expectsOutputToContain('Invalid --before date')
        ->assertExitCode(2);
});
