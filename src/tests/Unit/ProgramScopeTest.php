<?php

use App\Models\Channel;
use App\Models\Genre;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopeSearch is a no-op when term is null', function () {
    $count = Program::query()->search(null)->getQuery()->wheres;
    expect($count)->toBeEmpty();
});

it('scopeOfGenre filters via pivot', function () {
    $channel = Channel::factory()->create();
    $g       = Genre::factory()->create(['code' => 'DOC']);
    $p       = Program::factory()->create(['channel_id' => $channel->id]);
    $p->genres()->attach($g->id);

    Program::factory()->count(2)->create(['channel_id' => $channel->id]);

    $rows = Program::query()->ofGenre('DOC')->get();
    expect($rows->pluck('id')->all())->toBe([$p->id]);
});
