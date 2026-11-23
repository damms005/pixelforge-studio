<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Nanodollars;
use App\Billing\SpendReport;
use App\Http\Controllers\Controller;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpendReportController extends Controller
{
    public function __invoke(Request $request, SpendReport $report, PixelforgeClient $pixelforge): Response
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        $end = CarbonImmutable::parse($validated['end'] ?? now('UTC')->toDateString(), 'UTC');
        $start = CarbonImmutable::parse($validated['start'] ?? $end->subDays(13)->toDateString(), 'UTC');

        if ($start->diffInDays($end) > 91) {
            $start = $end->subDays(91);
        }

        $days = $report->between($start, $end);
        [$pixelforgeCosts, $pixelforgeError] = $this->pixelforgeCosts($pixelforge, $start, $end);

        $money = fn (int $nanodollars) => Nanodollars::toDecimal($nanodollars);

        return Inertia::render('admin/SpendReport', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'pixelforge_error' => $pixelforgeError,
            'days' => $days->reverse()->values()->map(fn (array $day) => [
                'date' => $day['date'],
                'generations' => $day['generations'],
                'charged' => $money($day['charged']),
                'provider_cost' => $money($day['provider_cost']),
                'profit' => $money($day['profit']),
                'pixelforge_cost' => isset($pixelforgeCosts[$day['date']]) ? $money($pixelforgeCosts[$day['date']]) : null,
                'matches_pixelforge' => isset($pixelforgeCosts[$day['date']]) ? $pixelforgeCosts[$day['date']] === $day['provider_cost'] : null,
                'users' => array_map(fn (array $user) => [
                    ...$user,
                    'charged' => $money($user['charged']),
                    'provider_cost' => $money($user['provider_cost']),
                    'profit' => $money($user['profit']),
                ], $day['users']),
            ]),
            'totals' => [
                'generations' => $days->sum('generations'),
                'charged' => $money($days->sum('charged')),
                'provider_cost' => $money($days->sum('provider_cost')),
                'profit' => $money($days->sum('profit')),
                'pixelforge_cost' => $pixelforgeCosts === null ? null : $money(array_sum($pixelforgeCosts)),
            ],
        ]);
    }

    /**
     * What Pixelforge billed this API key per day, the source of truth for provider cost.
     *
     * @return array{0: array<string, int>|null, 1: string|null}
     */
    private function pixelforgeCosts(PixelforgeClient $pixelforge, CarbonImmutable $start, CarbonImmutable $end): array
    {
        try {
            $usage = $pixelforge->usage($start->toDateString(), $end->toDateString());
        } catch (PixelforgeException $exception) {
            return [null, $exception->getMessage()];
        }

        $costs = [];

        foreach ($usage['data'] ?? [] as $day) {
            $costs[$day['date']] = Nanodollars::fromDecimal($day['cost_usd']);
        }

        return [$costs, null];
    }
}
