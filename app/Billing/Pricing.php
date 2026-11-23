<?php

namespace App\Billing;

use DomainException;

/**
 * The published pricing from GET /v1/pricing: rates plus the token rules needed to estimate
 * a generation before it runs.
 */
final readonly class Pricing
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public int $textTokenVariancePercent,
        public int $tokensPerReferenceImage,
        public array $outputTokens,
        public array $reasoningTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromApi(array $response): self
    {
        $tokens = $response['tokens'] ?? throw new DomainException('Pixelforge pricing has no token rules.');

        return new self(
            rates: Rates::fromArray($response['rates']),
            charactersPerTextToken: (int) $tokens['characters_per_text_token'],
            // Sent as a fraction (0.15). Kept as whole percent so the maths stays in integers.
            textTokenVariancePercent: (int) round((float) $tokens['text_token_variance'] * 100),
            tokensPerReferenceImage: (int) $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoningTokens: $tokens['reasoning'],
        );
    }

    /**
     * @return list<string>
     */
    public function sizes(): array
    {
        return array_keys(array_values($this->outputTokens)[0] ?? []);
    }

    /**
     * @return list<string>
     */
    public function qualities(): array
    {
        return array_keys($this->outputTokens);
    }
}
