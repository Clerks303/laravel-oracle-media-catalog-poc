<?php

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Genre;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('produces a stable database state when run twice', function () {
    $this->seed();

    $snapshot = [
        'users'      => User::count(),
        'genres'     => Genre::count(),
        'channels'   => Channel::count(),
        'programs'   => Program::count(),
        'broadcasts' => Broadcast::count(),
    ];

    expect($snapshot['genres'])->toBe(5);
    expect($snapshot['channels'])->toBe(2);
    expect($snapshot['programs'])->toBe(16); // 2 channels * 8 programs
    expect($snapshot['broadcasts'])->toBe(48); // 16 programs * 3 broadcasts

    // Re-seed: nothing should change.
    $this->seed();

    expect(User::count())->toBe($snapshot['users']);
    expect(Genre::count())->toBe($snapshot['genres']);
    expect(Channel::count())->toBe($snapshot['channels']);
    expect(Program::count())->toBe($snapshot['programs']);
    expect(Broadcast::count())->toBe($snapshot['broadcasts']);
});

it('keeps the editor user unique across multiple runs', function () {
    $this->seed();
    $this->seed();
    $this->seed();

    expect(User::where('email', 'editor@mediacat.local')->count())->toBe(1);
});

it('does not duplicate genre codes', function () {
    $this->seed();
    $this->seed();

    $codes = Genre::pluck('code')->all();
    expect($codes)->toEqual(array_unique($codes));
});
