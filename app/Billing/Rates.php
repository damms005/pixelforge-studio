<?php

namespace App\Billing;

use DomainException;

/**
 * Pixelforge rates, in US dollars per 1M tokens, for each token type.
 */
final readonly class Rates
{
    /**
     * @param  array<string, string>  $perMillionTokens  e.g. ['output_tokens' => '40.00', ...]
     */
    public function __construct(public array $perMillionTokens) {}

    /**
     * @param  array<string, string>  $rates
     */
    public static function fromArray(array $rates): self
    {
        foreach (TokenUsage::TYPES as $type) {
            if (! isset($rates[$type])) {
                throw new DomainException("Pixelforge rates are missing [{$type}].");
            }
        }

        return new self(array_intersect_key($rates, array_flip(TokenUsage::TYPES)));
    }

    /**
     * Exact provider cost of the given tokens, in nanodollars. Nothing is rounded: if a rate is
     * so precise that the cost has a fraction of a nanodollar, this fails loudly rather than
     * silently charging the wrong amount.
     */
    public function costOf(TokenUsage $usage): int
    {
        $total = 0;

        foreach ($usage->toArray() as $type => $tokens) {
            $nanodollarsPerMillionTokens = Nanodollars::fromDecimal($this->perMillionTokens[$type]);
            $product = $tokens * $nanodollarsPerMillionTokens;

            if ($product % 1_000_000 !== 0) {
                throw new DomainException("The {$type} cost has a fraction of a nanodollar at rate {$this->perMillionTokens[$type]}.");
            }

            $total += intdiv($product, 1_000_000);
        }

        return $total;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->perMillionTokens;
    }
}
