<?php

namespace App\Pixelforge;

use App\Billing\Pricing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    /**
     * Current published pricing. It needs no key and changes rarely, so it is cached briefly.
     * A generation is always billed at the rates on its own object, never at these.
     */
    public function pricing(): Pricing
    {
        $response = Cache::remember('pixelforge.pricing', now()->addMinutes(10), fn () => $this->send(
            fn (PendingRequest $request) => $request->get('/pricing'),
        ));

        return Pricing::fromApi($response);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed> the generation object
     */
    public function createGeneration(array $parameters, string $idempotencyKey): array
    {
        return $this->send(fn (PendingRequest $request) => $request
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/generations', $parameters));
    }

    /**
     * @return array<string, mixed> the generation object
     */
    public function getGeneration(string $id): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('/generations/'.rawurlencode($id)));
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('/usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $request = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);

        if ($this->apiKey) {
            $request->withToken($this->apiKey);
        }

        try {
            $response = $call($request);
        } catch (ConnectionException $exception) {
            throw new PixelforgeException(0, 'connection_error', 'Could not reach Pixelforge: '.$exception->getMessage());
        }

        if ($response->successful()) {
            return $response->json();
        }

        $error = $response->json('error') ?? [];
        $retryAfter = $response->header('Retry-After');

        throw new PixelforgeException(
            status: $response->status(),
            errorCode: $error['code'] ?? 'http_'.$response->status(),
            message: $error['message'] ?? 'Pixelforge returned HTTP '.$response->status().'.',
            retryAfterSeconds: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }
}
