<?php

namespace App\Generations;

use App\Billing\Ledger;
use App\Billing\Markup;
use App\Billing\Rates;
use App\Billing\TokenUsage;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only place a generation changes status. Webhooks, the create response and polling all
 * pass Pixelforge's generation object here, in any order and any number of times.
 *
 * Rules: the row is locked while it changes; a generation only moves forward
 * (pending → queued → processing → succeeded | failed); a final generation never changes again,
 * so its cost is recorded exactly once.
 */
class GenerationUpdater
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Markup $markup,
    ) {}

    /**
     * @param  array<string, mixed>  $object  a Pixelforge generation object
     */
    public function apply(Generation $generation, array $object): Generation
    {
        return DB::transaction(function () use ($generation, $object) {
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            // A webhook can arrive before the create request returns; link the ids either way.
            $generation->provider_id ??= $object['id'] ?? null;

            $incoming = GenerationStatus::tryFrom($object['status'] ?? '');

            if ($incoming === null || $generation->status->isFinal() || $incoming->rank() <= $generation->status->rank()) {
                $this->warnIfProviderDisagreesWithFinalState($generation, $incoming);
                $generation->save();

                return $generation;
            }

            $generation->status = $incoming;
            $generation->retry_reason = null;
            $generation->rates ??= $object['rates'] ?? null;
            $generation->provider_created_at ??= $this->time($object['created_at'] ?? null);
            $generation->started_at ??= $this->time($object['started_at'] ?? null);

            if ($incoming === GenerationStatus::Succeeded) {
                $this->recordSuccess($generation, $object);
            }

            if ($incoming === GenerationStatus::Failed) {
                $generation->error_code = $object['error']['code'] ?? 'generation_failed';
                $generation->error_message = $object['error']['message'] ?? 'The generation failed.';
                $generation->completed_at = $this->time($object['completed_at'] ?? null);
                $generation->charged_nanodollars = 0;
            }

            $generation->save();

            if ($incoming->isFinal()) {
                $this->ledger->settle($generation);
            }

            return $generation;
        });
    }

    /**
     * Fails a generation that Pixelforge never accepted (rejected, or gave up retrying).
     * Nothing was created there, so nothing is charged and the hold goes back.
     */
    public function failBeforeSubmission(Generation $generation, string $code, string $message): Generation
    {
        return DB::transaction(function () use ($generation, $code, $message) {
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($generation->status !== GenerationStatus::Pending) {
                return $generation;
            }

            $generation->status = GenerationStatus::Failed;
            $generation->error_code = $code;
            $generation->error_message = $message;
            $generation->retry_reason = null;
            $generation->charged_nanodollars = 0;
            $generation->save();

            $this->ledger->settle($generation);

            return $generation;
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function recordSuccess(Generation $generation, array $object): void
    {
        if (! is_array($object['usage'] ?? null)) {
            // Never record a succeeded generation as free; fail so the event is retried or polled.
            throw new DomainException("Pixelforge sent a succeeded generation without usage [{$generation->provider_id}].");
        }

        $usage = TokenUsage::fromArray($object['usage']);
        // Billed at the rates fixed when the generation was created, not today's rates.
        $rates = Rates::fromArray($object['rates'] ?? $generation->rates ?? []);

        $generation->usage = $object['usage'];
        $generation->rates = $rates->toArray();
        $generation->provider_cost_nanodollars = $rates->costOf($usage);
        $generation->charged_nanodollars = $this->markup->priceFor($generation->provider_cost_nanodollars);
        $generation->image_url = $object['image_url'] ?? null;
        $generation->image_url_expires_at = $this->time($object['image_url_expires_at'] ?? null);
        $generation->completed_at = $this->time($object['completed_at'] ?? null);

        if ($generation->charged_nanodollars > $generation->held_nanodollars) {
            Log::warning('Generation cost more than its hold.', ['generation' => $generation->id]);
        }
    }

    private function warnIfProviderDisagreesWithFinalState(Generation $generation, ?GenerationStatus $incoming): void
    {
        if ($generation->status === GenerationStatus::Failed && $incoming === GenerationStatus::Succeeded) {
            // Only possible if we gave up on a create request that Pixelforge did accept.
            Log::error('Pixelforge reports success for a generation we recorded as failed.', [
                'generation' => $generation->id,
                'provider_id' => $generation->provider_id,
            ]);
        }
    }

    private function time(?string $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value)->utc() : null;
    }
}
