<script setup lang="ts">
import { Head, router, useForm, useHttp, usePoll } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, onMounted, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import ReferenceImagesInput from '@/components/generations/ReferenceImagesInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatUsd } from '@/lib/money';
import { dashboard } from '@/routes';
import { store as buyCredits } from '@/routes/credits';
import { estimate as estimateRoute, store } from '@/routes/generations';
import type { Estimate, Generation, GenerationOptions } from '@/types/generations';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate images', href: dashboard() }],
    },
});

const props = defineProps<{
    options: GenerationOptions;
    balance: string;
    generations: Generation[];
}>();

const CREDIT_PACKS = [1, 5, 10];

const form = useForm<GenerationInput>({
    prompt: '',
    size: props.options.sizes[0] ?? '1024x1024',
    quality: props.options.qualities.includes('medium') ? 'medium' : props.options.qualities[0],
    reference_images: [] as string[],
});

type GenerationInput = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
};

const estimate = useHttp<GenerationInput, Estimate>({
    prompt: '',
    size: form.size,
    quality: form.quality,
    reference_images: [] as string[],
});

const refreshEstimate = useDebounceFn(() => {
    if (!form.prompt.trim()) {
        estimate.response = null;
        estimate.clearErrors();

        return;
    }

    estimate.prompt = form.prompt;
    estimate.size = form.size;
    estimate.quality = form.quality;
    estimate.reference_images = form.reference_images.filter((url) => url.trim() !== '');
    estimate.post(estimateRoute.url()).catch(() => {});
}, 300);

watch(() => [form.prompt, form.size, form.quality, [...form.reference_images]], refreshEstimate);

const formErrors = computed(() => form.errors as Record<string, string | undefined>);

const referenceImageError = computed(() =>
    Object.entries(formErrors.value).find(([field]) => field.startsWith('reference_images.'))?.[1],
);

const creditsError = computed(() => formErrors.value.credits);

const promptLength = computed(() => [...form.prompt].length);

const estimateError = computed(() => Object.values(estimate.errors)[0] as string | undefined);

const hasActiveGenerations = computed(() =>
    props.generations.some((generation) => ['pending', 'queued', 'processing'].includes(generation.status)),
);

const { start, stop } = usePoll(2500, { only: ['generations', 'balance'] }, { autoStart: false });

watch(hasActiveGenerations, (active) => (active ? start() : stop()));
onMounted(() => hasActiveGenerations.value && start());

function submit() {
    form.transform((data) => ({
        ...data,
        reference_images: data.reference_images.filter((url) => url.trim() !== ''),
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('prompt', 'reference_images');
            estimate.response = null;
        },
    });
}

function buy(dollars: number) {
    router.post(buyCredits.url(), { dollars }, { preserveScroll: true, only: ['balance', 'flash'] });
}
</script>

<template>
    <Head title="Generate images" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <form
                class="flex flex-col gap-5 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <div class="flex items-baseline justify-between">
                        <Label for="prompt">Prompt</Label>
                        <span
                            class="text-xs tabular-nums text-muted-foreground"
                            :class="{ 'text-red-600': promptLength > options.max_prompt_length }"
                        >
                            {{ promptLength.toLocaleString() }} / {{ options.max_prompt_length.toLocaleString() }}
                        </span>
                    </div>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="4"
                        required
                        placeholder="A lighthouse on a cliff at dawn, oil painting"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="size">Size</Label>
                        <select
                            id="size"
                            v-model="form.size"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                        >
                            <option v-for="size in options.sizes" :key="size" :value="size">
                                {{ size.replace('x', ' × ') }}
                            </option>
                        </select>
                        <InputError :message="form.errors.size" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Quality</Label>
                        <div class="inline-flex rounded-md border border-input p-0.5" role="radiogroup">
                            <button
                                v-for="quality in options.qualities"
                                :key="quality"
                                type="button"
                                role="radio"
                                :aria-checked="form.quality === quality"
                                class="flex-1 rounded px-3 py-1.5 text-sm capitalize transition"
                                :class="form.quality === quality ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="form.quality = quality"
                            >
                                {{ quality }}
                            </button>
                        </div>
                        <InputError :message="form.errors.quality" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <ReferenceImagesInput v-model="form.reference_images" :max="options.max_reference_images" />
                    <InputError :message="form.errors.reference_images ?? referenceImageError" />
                </div>

                <div class="rounded-lg bg-muted/50 p-4 text-sm">
                    <template v-if="estimate.response && !estimateError">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-muted-foreground">Estimated price</span>
                            <span class="text-lg font-semibold tabular-nums">
                                {{ formatUsd(estimate.response.min_price) }} – {{ formatUsd(estimate.response.max_price) }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Output tokens are fixed by size and quality. Prompt and reasoning tokens are only known when the
                            image is done, so we hold {{ formatUsd(estimate.response.max_price) }} and charge the exact cost
                            (Pixelforge's price + {{ options.markup_percent }}%) when it finishes. Failed images are free.
                        </p>
                        <p v-if="!estimate.response.affordable" class="mt-2 text-xs font-medium text-red-600">
                            Your balance is below {{ formatUsd(estimate.response.max_price) }}. Buy credits to generate this image.
                        </p>
                    </template>
                    <p v-else-if="estimateError" class="text-xs text-red-600">{{ estimateError }}</p>
                    <p v-else class="text-muted-foreground">Write a prompt to see the price.</p>
                </div>

                <InputError :message="creditsError" />

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing || !form.prompt.trim()">
                        {{ form.processing ? 'Submitting…' : 'Generate image' }}
                    </Button>
                </div>
            </form>

            <aside class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Wallet class="size-4" /> Credit balance
                </div>
                <div class="text-3xl font-semibold tabular-nums" :title="balance">{{ formatUsd(balance) }}</div>
                <p class="text-xs text-muted-foreground">
                    1 credit = 1 US dollar. The highest possible price is held while an image is in progress; the rest
                    comes back when it finishes.
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <Button v-for="dollars in CREDIT_PACKS" :key="dollars" variant="outline" @click="buy(dollars)">
                        +${{ dollars }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">Demo purchase: no payment is taken.</p>
            </aside>
        </div>

        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-semibold">Your images</h2>
            <p v-if="!generations.length" class="text-sm text-muted-foreground">
                Nothing yet. Your images and what each one cost appear here.
            </p>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <GenerationCard v-for="generation in generations" :key="generation.id" :generation="generation" />
            </div>
        </section>
    </div>
</template>
