<?php

use App\Models\Channel;
use App\Models\Genre;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters programs by search term (case-insensitive)', function () {
    $channel = Channel::factory()->create();
    Program::factory()->create(['channel_id' => $channel->id, 'title' => 'Berlin Underground']);
    Program::factory()->create(['channel_id' => $channel->id, 'title' => 'Paris by Night']);

    $this->getJson('/api/v1/programs?search=berlin')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Berlin Underground');
});

it('filters programs by genre code', function () {
    $channel = Channel::factory()->create();
    $doc     = Genre::factory()->create(['code' => 'DOC', 'label' => 'Documentary']);
    $news    = Genre::factory()->create(['code' => 'NEWS', 'label' => 'News']);

    $p1 = Program::factory()->create(['channel_id' => $channel->id]);
    $p2 = Program::factory()->create(['channel_id' => $channel->id]);

    $p1->genres()->attach($doc->id);
    $p2->genres()->attach($news->id);

    $this->getJson('/api/v1/programs?genre=DOC')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $p1->id);
});
