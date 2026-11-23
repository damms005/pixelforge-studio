<?php

namespace App\Pixelforge;

use RuntimeException;

class PixelforgeException extends RuntimeException
{
    /** Codes where nothing was created and the same request may succeed later. */
    private const RETRYABLE_CODES = ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'];

    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Retrying is safe: the create request carries an Idempotency-Key, so Pixelforge never
     * makes a second generation for it. A connection error has status 0.
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true)
            || $this->status === 0
            || $this->status >= 500;
    }
}
