<?php

namespace App\Generations;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookEventHandler
{
    public function __construct(private readonly GenerationUpdater $updater) {}

    /**
     * Records the event id and applies the event in one transaction. A repeated event id is
     * skipped; if applying fails, the id is rolled back too, so Pixelforge's retry is processed.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        DB::transaction(function () use ($event) {
            $object = $event['data']['object'] ?? [];

            $isNew = DB::table('webhook_events')->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'provider_generation_id' => $object['id'] ?? null,
                'payload' => json_encode($event),
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if (! $isNew || ! str_starts_with($event['type'], 'generation.')) {
                return;
            }

            $generation = $this->findGeneration($object);

            if ($generation === null) {
                Log::warning('Pixelforge webhook for an unknown generation.', ['event' => $event['id'], 'generation' => $object['id'] ?? null]);

                return;
            }

            $this->updater->apply($generation, $object);
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        if (isset($object['id']) && $generation = Generation::where('provider_id', $object['id'])->first()) {
            return $generation;
        }

        $uuid = $object['metadata']['generation_uuid'] ?? null;

        return $uuid ? Generation::where('uuid', $uuid)->first() : null;
    }
}
