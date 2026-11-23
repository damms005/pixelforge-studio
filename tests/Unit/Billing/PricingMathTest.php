<?php

namespace Tests\Unit\Billing;

use App\Billing\CostEstimator;
use App\Billing\Markup;
use App\Billing\Nanodollars;
use App\Billing\Pricing;
use App\Billing\Rates;
use App\Billing\TokenUsage;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\FakesPixelforge;

class PricingMathTest extends TestCase
{
    use FakesPixelforge;

    public function test_nanodollars_round_trip_exact_decimal_strings(): void
    {
        $this->assertSame(46_125_000, Nanodollars::fromDecimal('0.046125000'));
        $this->assertSame(1, Nanodollars::fromDecimal('0.000000001'));
        $this->assertSame(12_500_000_000, Nanodollars::fromDecimal('12.50'));
        $this->assertSame('0.373875000', Nanodollars::toDecimal(373_875_000));
        $this->assertSame('-1.000000005', Nanodollars::toDecimal(-1_000_000_005));
    }

    public function test_amounts_finer_than_a_nanodollar_are_rejected_not_rounded(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Nanodollars::fromDecimal('0.0000000001');
    }

    /**
     * Figures from the Pixelforge docs.
     */
    #[DataProvider('documentedCosts')]
    public function test_provider_cost_matches_the_documented_examples(array $usage, string $expected): void
    {
        $cost = Rates::fromArray(self::RATES)->costOf(TokenUsage::fromArray($usage));

        $this->assertSame($expected, Nanodollars::toDecimal($cost));
    }

    public static function documentedCosts(): array
    {
        return [
            'succeeded generation example' => [
                ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184],
                '0.305510000',
            ],
            'usage endpoint example' => [
                ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950],
                '0.169080000',
            ],
            'one text token' => [
                ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0],
                '0.000005000',
            ],
        ];
    }

    public function test_a_rate_that_would_need_a_fraction_of_a_nanodollar_fails_loudly(): void
    {
        $rates = Rates::fromArray([...self::RATES, 'reasoning_tokens' => '12.3456']);

        $this->expectException(DomainException::class);

        $rates->costOf(new TokenUsage(0, 0, 0, 3));
    }

    public function test_markup_adds_25_percent_and_rounds_a_fraction_of_a_nanodollar_up(): void
    {
        $markup = new Markup(25);

        $this->assertSame(381_887_500, $markup->priceFor(305_510_000));
        $this->assertSame(2, $markup->priceFor(1));
        $this->assertSame(0, $markup->priceFor(0));
    }

    public function test_the_highest_estimate_matches_the_documented_worst_case(): void
    {
        $estimate = (new CostEstimator(new Markup(25)))->estimate(
            Pricing::fromApi(self::pricingResponse()),
            prompt: str_repeat('a', 400),
            size: '1536x1024',
            quality: 'high',
            referenceImages: ['https://example.com/a.png', 'https://example.com/b.png'],
        );

        // Docs: 115 text + 1,530 image + 6,450 output + 8,000 reasoning tokens = $0.373875.
        $this->assertSame(115, $estimate->maxTokens->inputTextTokens);
        $this->assertSame('0.373875000', Nanodollars::toDecimal($estimate->maxProviderCostNanodollars));
        $this->assertSame('0.467343750', Nanodollars::toDecimal($estimate->maxPriceNanodollars));

        // Lowest case: 85 text tokens and 1,000 reasoning tokens.
        $this->assertSame(85, $estimate->minTokens->inputTextTokens);
        $this->assertSame('0.286225000', Nanodollars::toDecimal($estimate->minProviderCostNanodollars));
    }

    public function test_text_tokens_count_unicode_characters_not_bytes(): void
    {
        $estimate = (new CostEstimator(new Markup(25)))->estimate(
            Pricing::fromApi(self::pricingResponse()), str_repeat('é', 8), '1024x1024', 'low', [],
        );

        $this->assertSame(3, $estimate->maxTokens->inputTextTokens); // ceil(2 * 1.15)
    }
}
