<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Generations\GenerationUpdater;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a pending generation to Pixelforge. Rate, concurrency and overload rejections create
 * nothing there, so the job waits for Retry-After and tries again with the same
 * Idempotency-Key. It gives up after a capped number of attempts and refunds the hold.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 25;

    public int $timeout = 60;

    private const MAX_DELAY_SECONDS = 60;

    public function __construct(public readonly int $generationId) {}

    public function handle(PixelforgeClient $pixelforge, GenerationUpdater $updater): void
    {
        $generation = Generation::find($this->generationId);

        if ($generation === null || $generation->status !== GenerationStatus::Pending) {
            return;
        }

        $generation->increment('submit_attempts');

        try {
            $object = $pixelforge->createGeneration([
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_uuid' => $generation->uuid],
            ], idempotencyKey: $generation->uuid);
        } catch (PixelforgeException $exception) {
            if (! $exception->isRetryable()) {
                $updater->failBeforeSubmission($generation, $exception->errorCode, $this->messageFor($exception));

                return;
            }

            $generation->update(['retry_reason' => $exception->errorCode]);
            $this->release($this->delayFor($exception));

            return;
        }

        $updater->apply($generation, $object);
    }

    public function failed(?Throwable $exception): void
    {
        $generation = Generation::find($this->generationId);

        if ($generation) {
            app(GenerationUpdater::class)->failBeforeSubmission(
                $generation,
                'submission_failed',
                'Pixelforge stayed busy or unreachable, so we stopped trying. You were not charged.',
            );
        }
    }

    /**
     * Retry-After when Pixelforge sends one, growing with each attempt, capped, plus jitter so
     * waiting generations do not all retry in the same second.
     */
    private function delayFor(PixelforgeException $exception): int
    {
        $base = max($exception->retryAfterSeconds ?? 0, min(2 ** $this->attempts(), self::MAX_DELAY_SECONDS));

        return min($base, self::MAX_DELAY_SECONDS) + random_int(0, 3);
    }

    private function messageFor(PixelforgeException $exception): string
    {
        $reason = match ($exception->errorCode) {
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy.',
            'daily_limit_exceeded' => 'The daily Pixelforge limit is used up. Try again after 00:00 UTC.',
            'invalid_api_key' => 'The image service is not set up correctly. We have been notified.',
            default => $exception->getMessage(),
        };

        if ($exception->errorCode === 'invalid_api_key') {
            report($exception);
        }

        return $reason.' You were not charged.';
    }
}
