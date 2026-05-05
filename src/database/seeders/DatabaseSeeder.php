<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Genre;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent: running `db:seed` twice yields the same database state.
 *
 * - Genres / channels / users: `firstOrCreate` keyed on a natural key (code, email).
 * - Programs: only seeded for a channel that has none yet — we do not re-generate
 *   Faker payloads on top of an already-populated channel.
 * - Broadcasts: only seeded for programs that have none yet, with a linear 6h slot
 *   schedule per channel so the anti-overlap trigger never fires.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'editor@arte.local'],
            [
                'name'     => 'ARTE Editor',
                'password' => Hash::make('password'),
            ],
        );

        $genres = collect([
            ['code' => 'DOC',     'label' => 'Documentary'],
            ['code' => 'FICTION', 'label' => 'Fiction'],
            ['code' => 'NEWS',    'label' => 'News'],
            ['code' => 'CULTURE', 'label' => 'Culture'],
            ['code' => 'CINEMA',  'label' => 'Cinema'],
        ])->map(fn ($g) => Genre::firstOrCreate(['code' => $g['code']], $g));

        $channels = collect([
            ['code' => 'ARTEFR', 'name' => 'ARTE France',      'country' => 'FR', 'language' => 'fr'],
            ['code' => 'ARTEDE', 'name' => 'ARTE Deutschland', 'country' => 'DE', 'language' => 'de'],
        ])->map(fn ($c) => Channel::firstOrCreate(['code' => $c['code']], $c));

        $channels->each(function (Channel $channel) use ($genres) {
            if ($channel->programs()->exists()) {
                return; // already populated — don't re-seed editorial content
            }

            $slot = 0;
            $base = now()->subMonth()->startOfHour();

            Program::factory()->count(8)->create(['channel_id' => $channel->id])
                ->each(function (Program $program) use ($genres, $channel, &$slot, $base) {
                    $program->genres()->attach(
                        $genres->random(rand(1, 2))->pluck('id')->all()
                    );

                    if ($program->broadcasts()->exists()) {
                        return;
                    }

                    for ($i = 0; $i < 3; $i++) {
                        Broadcast::factory()->create([
                            'program_id'   => $program->id,
                            'channel_id'   => $channel->id,
                            'scheduled_at' => $base->copy()->addHours(6 * $slot++),
                        ]);
                    }
                });
        });
    }
}
