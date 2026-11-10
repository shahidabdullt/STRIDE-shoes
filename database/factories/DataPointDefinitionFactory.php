<?php

namespace Database\Factories;

use App\Models\DataPointDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DataPointDefinition> */
class DataPointDefinitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'dp_'.fake()->unique()->lexify('??????'),
            'label' => 'Shoe size',
            'instruction' => 'The US shoe size the customer says they wear.',
            'type' => 'number',
            'options' => null,
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
