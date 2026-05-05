<?php

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('rejects unauthenticated broadcast creation', function () {
    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id]);

    $this->postJson('/api/v1/broadcasts', [
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ])->assertUnauthorized();
});

it('creates a broadcast when authenticated', function () {
    Sanctum::actingAs(User::factory()->create());

    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id]);
    $when    = now()->addDay()->startOfHour();

    $this->postJson('/api/v1/broadcasts', [
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => $when->toDateTimeString(),
        'replay_until' => $when->copy()->addDays(7)->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.program.id', $program->id);

    expect(Broadcast::count())->toBe(1);
});

it('rejects replay_until before scheduled_at', function () {
    Sanctum::actingAs(User::factory()->create());

    $channel = Channel::factory()->create();
    $program = Program::factory()->create(['channel_id' => $channel->id]);

    $this->postJson('/api/v1/broadcasts', [
        'program_id'   => $program->id,
        'channel_id'   => $channel->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'replay_until' => now()->subDay()->toDateTimeString(),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('replay_until');
});
