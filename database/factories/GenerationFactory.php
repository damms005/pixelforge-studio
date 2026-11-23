<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'estimated_min_nanodollars' => 58_000_000,
            'estimated_max_nanodollars' => 86_000_000,
            'held_nanodollars' => 86_000_000,
        ];
    }

    public function submitted(GenerationStatus $status = GenerationStatus::Queued): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'provider_id' => 'gen_'.Str::upper(Str::random(26)),
        ]);
    }
}
