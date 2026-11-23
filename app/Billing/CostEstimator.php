<?php

namespace App\Billing;

final readonly class CostEstimator
{
    public function __construct(private Markup $markup) {}

    /**
     * @param  list<string>  $referenceImages
     */
    public function estimate(Pricing $pricing, string $prompt, string $size, string $quality, array $referenceImages): Estimate
    {
        $estimatedTextTokens = (int) ceil(mb_strlen($prompt) / $pricing->charactersPerTextToken);
        $variance = $pricing->textTokenVariancePercent;

        $imageTokens = count($referenceImages) * $pricing->tokensPerReferenceImage;
        $outputTokens = $pricing->outputTokens[$quality][$size];
        $reasoning = $pricing->reasoningTokens[$quality];

        $minTokens = new TokenUsage(
            inputTextTokens: intdiv($estimatedTextTokens * (100 - $variance), 100),
            inputImageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningTokens: (int) $reasoning['min'],
        );

        $maxTokens = new TokenUsage(
            inputTextTokens: intdiv($estimatedTextTokens * (100 + $variance) + 99, 100),
            inputImageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningTokens: (int) $reasoning['max'],
        );

        $minCost = $pricing->rates->costOf($minTokens);
        $maxCost = $pricing->rates->costOf($maxTokens);

        return new Estimate(
            minProviderCostNanodollars: $minCost,
            maxProviderCostNanodollars: $maxCost,
            minPriceNanodollars: $this->markup->priceFor($minCost),
            maxPriceNanodollars: $this->markup->priceFor($maxCost),
            minTokens: $minTokens,
            maxTokens: $maxTokens,
        );
    }
}
