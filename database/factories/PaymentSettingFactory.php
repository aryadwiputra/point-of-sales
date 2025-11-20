<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentSetting>
 */
class PaymentSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'default_gateway' => 'cash',
            'midtrans_enabled' => false,
            'midtrans_server_key' => null,
            'midtrans_client_key' => null,
            'midtrans_production' => false,
            'xendit_enabled' => false,
            'xendit_secret_key' => null,
            'xendit_public_key' => null,
            'xendit_production' => false,
        ];
    }
}
