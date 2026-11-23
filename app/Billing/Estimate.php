<?php

namespace App\Billing;

/**
 * What a generation can cost the user before it runs. Text and reasoning tokens are only
 * known once it completes, so the price is a range.
 */
final readonly class Estimate
{
    public function __construct(
        public int $minProviderCostNanodollars,
        public int $maxProviderCostNanodollars,
        public int $minPriceNanodollars,
        public int $maxPriceNanodollars,
        public TokenUsage $minTokens,
        public TokenUsage $maxTokens,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'min_price' => Nanodollars::toDecimal($this->minPriceNanodollars),
            'max_price' => Nanodollars::toDecimal($this->maxPriceNanodollars),
            'min_tokens' => $this->minTokens->toArray(),
            'max_tokens' => $this->maxTokens->toArray(),
        ];
    }
}
