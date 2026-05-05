<?php

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class BroadcastFactory extends Factory
{
    protected $model = Broadcast::class;

    public function definition(): array
    {
        $scheduled = $this->faker->dateTimeBetween('-1 month', '+1 month');
        return [
            'program_id'   => Program::factory(),
            'channel_id'   => Channel::factory(),
            'scheduled_at' => $scheduled,
            'replay_until' => (clone $scheduled)->modify('+7 days'),
        ];
    }
}
