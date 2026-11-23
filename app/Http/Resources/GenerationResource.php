<?php

namespace App\Http\Resources;

use App\Billing\Nanodollars;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageExpired = $this->image_url_expires_at?->isPast() ?? false;

        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'estimated_min' => Nanodollars::toDecimal($this->estimated_min_nanodollars),
            'estimated_max' => Nanodollars::toDecimal($this->estimated_max_nanodollars),
            'charged' => $this->charged_nanodollars === null ? null : Nanodollars::toDecimal($this->charged_nanodollars),
            'usage' => $this->usage,
            'image_url' => $imageExpired ? null : $this->image_url,
            'image_expired' => $imageExpired,
            'error_message' => $this->error_message,
            'retry_reason' => $this->retry_reason,
            'submit_attempts' => $this->submit_attempts,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
        ];
    }
}
