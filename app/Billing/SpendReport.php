<?php

namespace App\Billing;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Credit spend, provider cost and profit per UTC day and per user. Days follow Pixelforge's
 * billing rule: a generation counts on the UTC day it completed.
 */
class SpendReport
{
    /**
     * @return Collection<string, array{date: string, generations: int, charged: int, provider_cost: int, profit: int, users: list<array<string, mixed>>}>
     */
    public function between(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $rows = Generation::query()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->where('generations.status', GenerationStatus::Succeeded)
            ->where('generations.completed_at', '>=', $start->startOfDay())
            ->where('generations.completed_at', '<', $end->addDay()->startOfDay())
            ->selectRaw('DATE(generations.completed_at) as day, users.id as user_id, users.name, users.email')
            ->selectRaw('COUNT(*) as generations')
            ->selectRaw('SUM(generations.charged_nanodollars) as charged')
            ->selectRaw('SUM(generations.provider_cost_nanodollars) as provider_cost')
            ->groupBy('day', 'users.id', 'users.name', 'users.email')
            ->orderBy('day')
            ->orderBy('users.name')
            ->toBase()
            ->get()
            ->groupBy('day');

        $days = collect();

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();

            $users = collect($rows->get($date, []))->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'generations' => (int) $row->generations,
                'charged' => (int) $row->charged,
                'provider_cost' => (int) $row->provider_cost,
                'profit' => (int) $row->charged - (int) $row->provider_cost,
            ])->values();

            $days->put($date, [
                'date' => $date,
                'generations' => $users->sum('generations'),
                'charged' => $users->sum('charged'),
                'provider_cost' => $users->sum('provider_cost'),
                'profit' => $users->sum('profit'),
                'users' => $users->all(),
            ]);
        }

        return $days;
    }
}
