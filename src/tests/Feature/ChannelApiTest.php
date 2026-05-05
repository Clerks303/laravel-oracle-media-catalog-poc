<?php

use App\Models\Channel;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists channels', function () {
    Channel::factory()->count(3)->create();

    $this->getJson('/api/v1/channels')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'country', 'language']]])
        ->assertJsonCount(3, 'data');
});

it('returns programs for a channel', function () {
    $channel = Channel::factory()->create();
    Program::factory()->count(4)->create(['channel_id' => $channel->id]);

    $this->getJson("/api/v1/channels/{$channel->id}/programs")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('returns 404 for unknown channel', function () {
    $this->getJson('/api/v1/channels/999999')->assertNotFound();
});
