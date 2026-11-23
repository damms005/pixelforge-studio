<?php

namespace App\Enums;

enum GenerationStatus: string
{
    /** Saved here, not yet accepted by Pixelforge (waiting to submit, or retrying after a limit). */
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Position in the lifecycle. A generation only ever moves to a higher rank, so a late or
     * repeated event can never move it back.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Queued, self::Processing];
    }
}
