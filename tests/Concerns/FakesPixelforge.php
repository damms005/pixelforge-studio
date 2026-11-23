<?php

namespace Tests\Concerns;

use App\Billing\Ledger;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

trait FakesPixelforge
{
    protected const WEBHOOK_SECRET = 'whsec_test_secret';

    protected const RATES = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    protected function setUpFakesPixelforge(): void
    {
        config([
            'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
            'services.pixelforge.api_key' => 'pf_test_key',
            'services.pixelforge.webhook_secret' => self::WEBHOOK_SECRET,
            'services.pixelforge.markup_percent' => 25,
        ]);
    }

    /**
     * @param  array<string, mixed>  $responses  extra URL patterns => responses
     */
    protected function fakePixelforge(array $responses = []): void
    {
        Http::preventStrayRequests();
        Http::fake([...$responses, 'pixelforge.test/v1/pricing' => Http::response(self::pricingResponse())]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function pricingResponse(): array
    {
        return [
            'object' => 'pricing',
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'rates' => self::RATES,
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }

    /**
     * A generation that went through the normal submit path: its hold is already taken.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function heldGeneration(User $user, array $attributes = [], GenerationStatus $status = GenerationStatus::Queued): Generation
    {
        $generation = Generation::factory()
            ->for($user)
            ->when($status !== GenerationStatus::Pending, fn ($factory) => $factory->submitted($status))
            ->create($attributes);

        $this->assertTrue(app(Ledger::class)->hold($generation));

        return $generation;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function generationObject(Generation $generation, string $status, array $overrides = []): array
    {
        return array_replace([
            'id' => $generation->provider_id,
            'object' => 'generation',
            'status' => $status,
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_images' => $generation->reference_images,
            'metadata' => ['generation_uuid' => $generation->uuid],
            'image_url' => $status === 'succeeded' ? "https://pixelforge.test/images/{$generation->provider_id}.png" : null,
            'image_url_expires_at' => $status === 'succeeded' ? now()->addDay()->toIso8601ZuluString() : null,
            // Docs example: 12 + 765 + 6450 + 3184 tokens = 0.305510000 USD.
            'usage' => $status === 'succeeded' ? [
                'input_text_tokens' => 12,
                'input_image_tokens' => 765,
                'output_tokens' => 6450,
                'reasoning_tokens' => 3184,
                'total_tokens' => 10411,
            ] : null,
            'rates' => self::RATES,
            'error' => $status === 'failed' ? ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'] : null,
            'created_at' => now()->subSeconds(30)->toIso8601ZuluString(),
            'started_at' => in_array($status, ['processing', 'succeeded', 'failed']) ? now()->subSeconds(20)->toIso8601ZuluString() : null,
            'completed_at' => in_array($status, ['succeeded', 'failed']) ? now()->toIso8601ZuluString() : null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function postWebhook(array $object, string $eventId, ?string $secret = null, ?int $timestamp = null): TestResponse
    {
        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'generation.'.$object['status'],
            'created_at' => now()->toIso8601ZuluString(),
            'data' => ['object' => $object],
        ]);

        $timestamp ??= now()->getTimestamp();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::WEBHOOK_SECRET);

        return $this->call('POST', route('webhooks.pixelforge'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], content: $payload);
    }
}
