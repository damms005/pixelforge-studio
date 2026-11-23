<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Webhook delivery is not guaranteed. Generations that have not changed for a while are
 * fetched from Pixelforge and go through the same updater as webhooks.
 */
#[Signature('generations:sync {--stale-after=90 : Seconds without a change before a generation is polled} {--limit=20}')]
#[Description('Poll Pixelforge for queued or processing generations that have gone quiet')]
class SyncActiveGenerations extends Command
{
    public function handle(PixelforgeClient $pixelforge, GenerationUpdater $updater): int
    {
        $generations = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('provider_id')
            ->where('updated_at', '<', now()->subSeconds((int) $this->option('stale-after')))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($generations as $generation) {
            try {
                $updated = $updater->apply($generation, $pixelforge->getGeneration($generation->provider_id));
            } catch (PixelforgeException $exception) {
                $this->warn("{$generation->provider_id}: {$exception->errorCode}");

                if ($exception->errorCode === 'rate_limit_exceeded') {
                    break;
                }

                continue;
            }

            // Still running: push it back in the queue so other quiet generations get polled too.
            if (! $updated->status->isFinal()) {
                $updated->touch();
            }

            $this->line("{$generation->provider_id}: {$updated->status->value}");
        }

        return self::SUCCESS;
    }
}
