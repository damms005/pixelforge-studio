<script setup lang="ts">
import { computed } from 'vue';
import type { GenerationStatus } from '@/types/generations';

const props = defineProps<{
    status: GenerationStatus;
    retryReason?: string | null;
}>();

const styles: Record<GenerationStatus, string> = {
    pending: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    queued: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    processing: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    succeeded: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    failed: 'bg-red-500/15 text-red-700 dark:text-red-300',
};

const label = computed(() => {
    if (props.status === 'pending') {
        return props.retryReason ? 'Waiting for Pixelforge' : 'Submitting';
    }

    return props.status.charAt(0).toUpperCase() + props.status.slice(1);
});

const isActive = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.status),
);
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
            styles[status],
        ]"
    >
        <span
            v-if="isActive"
            class="size-1.5 animate-pulse rounded-full bg-current"
        />
        {{ label }}
    </span>
</template>
