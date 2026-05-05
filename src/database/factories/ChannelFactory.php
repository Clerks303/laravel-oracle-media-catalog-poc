<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'code'     => strtoupper($this->faker->unique()->lexify('CH???')),
            'name'     => $this->faker->company() . ' TV',
            'country'  => $this->faker->randomElement(['FR', 'DE', 'BE', 'CH']),
            'language' => $this->faker->randomElement(['fr', 'de', 'en']),
        ];
    }
}
