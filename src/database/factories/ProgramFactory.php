<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'channel_id'   => Channel::factory(),
            'title'        => $this->faker->sentence(4),
            'synopsis'     => $this->faker->paragraphs(3, true),
            'duration_min' => $this->faker->numberBetween(15, 180),
        ];
    }
}
