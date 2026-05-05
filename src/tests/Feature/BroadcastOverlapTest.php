<?php

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('rejects an overlapping broadcast on the same channel via API', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create([
        'channel_id'   => $channel->id,
        'duration_min' => 60,
    ]);

    Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ]);

    // Starts 30 min into the existing broadcast → overlap.
    $this->postJson('/api/v1/broadcasts', [
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:30:00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('scheduled_at');
});

it('allows a broadcast adjacent to an existing one (A.end == B.start)', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create([
        'channel_id'   => $channel->id,
        'duration_min' => 60,
    ]);

    Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ]);

    $this->postJson('/api/v1/broadcasts', [
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 21:00:00',
    ])->assertCreated();
});

it('allows the same time slot on a different channel', function () {
    $c1 = Channel::factory()->create();
    $c2 = Channel::factory()->create();
    $p1 = Program::factory()->create(['channel_id' => $c1->id, 'duration_min' => 60]);
    $p2 = Program::factory()->create(['channel_id' => $c2->id, 'duration_min' => 60]);

    Broadcast::factory()->create([
        'program_id'   => $p1->id,
        'channel_id'   => $c1->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ]);

    $this->postJson('/api/v1/broadcasts', [
        'program_id'   => $p2->id,
        'channel_id'   => $c2->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ])->assertCreated();
});

it('allows updating a broadcast without changing its time (self-match skipped)', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    $broadcast = Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ]);

    $this->patchJson("/api/v1/broadcasts/{$broadcast->id}", [
        'replay_until' => '2026-06-15 00:00:00',
    ])->assertOk();
});

it('rejects an update that moves the broadcast onto an existing one', function () {
    $channel = Channel::factory()->create();
    $p1 = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);
    $p2 = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    Broadcast::factory()->create([
        'program_id'   => $p1->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ]);
    $b2 = Broadcast::factory()->create([
        'program_id'   => $p2->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-02 20:00:00',
    ]);

    $this->patchJson("/api/v1/broadcasts/{$b2->id}", [
        'scheduled_at' => '2026-06-01 20:30:00',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('scheduled_at');
});

it('enforces non-overlap at the DB layer (Oracle trigger)', function () {
    if (DB::connection()->getDriverName() !== 'oracle') {
        $this->markTestSkipped('broadcasts_no_overlap is an Oracle compound trigger.');
    }

    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id, 'duration_min' => 60]);

    Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:00:00',
    ]);

    // yajra/oci8 surfaces Oci8Exception (PDOException-derived) rather than
    // wrapping in Illuminate\Database\QueryException, so we match by ORA code.
    expect(fn () => Broadcast::factory()->create([
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => '2026-06-01 20:30:00',
    ]))->toThrow(\PDOException::class, 'ORA-20010');
});
