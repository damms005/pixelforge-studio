<?php

namespace App\Http\Requests;

use App\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mirrors Pixelforge's own limits, so a request we accept is one Pixelforge accepts too.
 */
class GenerationRequest extends FormRequest
{
    public const MAX_PROMPT_LENGTH = 4000;

    public const MAX_REFERENCE_IMAGES = 4;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pricing = app(PixelforgeClient::class)->pricing();

        return [
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::in($pricing->sizes())],
            'quality' => ['required', Rule::in($pricing->qualities())],
            'reference_images' => ['present', 'array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_images.*.url' => 'Each reference image must be an http or https URL.',
            'reference_images.max' => 'Use at most '.self::MAX_REFERENCE_IMAGES.' reference images.',
        ];
    }

    /**
     * @return list<string>
     */
    public function referenceImages(): array
    {
        return array_values($this->validated('reference_images', []));
    }
}
