<script setup lang="ts">
import { CircleAlert, ImageOff, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import StatusBadge from '@/components/generations/StatusBadge.vue';
import Money from '@/components/money/Money.vue';
import MoneyRange from '@/components/money/MoneyRange.vue';
import type { Generation } from '@/types/generations';

const props = defineProps<{ generation: Generation }>();

const [width, height] = props.generation.size.split('x').map(Number);

const retryLabels: Record<string, string> = {
    rate_limit_exceeded: 'Pixelforge rate limit reached',
    concurrency_limit_exceeded: 'Pixelforge is busy with other images',
    overloaded: 'Pixelforge is overloaded',
};

const waitingNote = computed(() => {
    const reason = props.generation.retry_reason;

    if (props.generation.status !== 'pending' || !reason) {
        return null;
    }

    return `${retryLabels[reason] ?? 'Pixelforge did not answer'}. Retrying automatically (attempt ${props.generation.submit_attempts}).`;
});

const createdAt = computed(() =>
    new Date(props.generation.created_at).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }),
);
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
    >
        <div
            class="relative flex items-center justify-center bg-muted/50"
            :style="{ aspectRatio: `${width} / ${height}` }"
        >
            <img
                v-if="generation.image_url"
                :src="generation.image_url"
                :alt="generation.prompt"
                class="size-full object-cover"
                loading="lazy"
            />
            <div
                v-else-if="generation.status === 'succeeded'"
                class="flex flex-col items-center gap-2 p-4 text-center text-sm text-muted-foreground"
            >
                <ImageOff class="size-6" />
                The image link expired 24 hours after it was made.
            </div>
            <div
                v-else-if="generation.status === 'failed'"
                class="flex flex-col items-center gap-2 p-4 text-center text-sm text-red-700 dark:text-red-300"
            >
                <CircleAlert class="size-6" />
                {{ generation.error_message }}
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-2 p-4 text-center text-sm text-muted-foreground"
            >
                <LoaderCircle class="size-6 animate-spin" />
                <span v-if="waitingNote">{{ waitingNote }}</span>
                <span v-else-if="generation.status === 'processing'">Drawing…</span>
                <span v-else>In the queue…</span>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <StatusBadge
                    :status="generation.status"
                    :retry-reason="generation.retry_reason"
                />
                <span class="text-xs text-muted-foreground">{{ createdAt }}</span>
            </div>

            <p class="line-clamp-3 text-sm" :title="generation.prompt">
                {{ generation.prompt }}
            </p>

            <p class="text-xs text-muted-foreground">
                {{ generation.size.replace('x', ' × ') }} · {{ generation.quality }} quality
                <template v-if="generation.reference_images.length">
                    · {{ generation.reference_images.length }} reference
                    {{ generation.reference_images.length === 1 ? 'image' : 'images' }}
                </template>
            </p>

            <dl
                class="mt-auto grid grid-cols-2 gap-2 border-t pt-3 text-xs"
            >
                <div>
                    <dt class="text-muted-foreground">Estimated</dt>
                    <dd class="font-medium">
                        <MoneyRange :min="generation.estimated_min" :max="generation.estimated_max" />
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Actual cost</dt>
                    <dd class="font-medium">
                        <template v-if="generation.status === 'failed'">
                            <Money amount="0" /> (not charged)
                        </template>
                        <Money v-else-if="generation.charged !== null" :amount="generation.charged" />
                        <template v-else>Known when finished</template>
                    </dd>
                </div>
            </dl>
        </div>
    </article>
</template>
