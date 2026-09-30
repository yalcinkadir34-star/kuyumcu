<?php

namespace Database\Factories;

use App\Enums\CashRegisterType;
use App\Models\CashRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Kasa',
            'type' => CashRegisterType::Nakit,
            'is_active' => true,
        ];
    }
}
