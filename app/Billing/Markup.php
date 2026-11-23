<?php

namespace App\Billing;

/**
 * Users pay Pixelforge's cost plus a markup. A fraction of a nanodollar is rounded up, so the
 * price never falls below cost plus markup (the most it can be over is 1 nanodollar).
 */
final readonly class Markup
{
    public function __construct(public int $percent) {}

    public function priceFor(int $providerCostNanodollars): int
    {
        $numerator = $providerCostNanodollars * (100 + $this->percent);

        return intdiv($numerator + 99, 100);
    }
}
