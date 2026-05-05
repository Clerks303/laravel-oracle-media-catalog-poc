<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('G?????'));
        return [
            'code'  => $code,
            'label' => ucfirst($this->faker->word()),
        ];
    }
}
