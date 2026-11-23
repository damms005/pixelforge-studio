export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export type TokenCounts = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
};

export type Generation = {
    id: string;
    status: GenerationStatus;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    estimated_min: string;
    estimated_max: string;
    charged: string | null;
    usage: (TokenCounts & { total_tokens?: number }) | null;
    image_url: string | null;
    image_expired: boolean;
    error_message: string | null;
    retry_reason: string | null;
    submit_attempts: number;
    created_at: string;
    completed_at: string | null;
};

export type Estimate = {
    min_price: string;
    max_price: string;
    min_tokens: TokenCounts;
    max_tokens: TokenCounts;
    affordable: boolean;
};

export type GenerationOptions = {
    sizes: string[];
    qualities: string[];
    max_prompt_length: number;
    max_reference_images: number;
    markup_percent: number;
};
