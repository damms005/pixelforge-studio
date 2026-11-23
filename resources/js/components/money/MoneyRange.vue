<script setup lang="ts">
import { exactUsd, formatUsdRange } from '@/lib/money';

defineProps<{ min: string; max: string }>();
</script>

<template>
    <span
        class="tabular-nums"
        :title="`Exact: ${exactUsd(min)} – ${exactUsd(max)}`"
        >{{ formatUsdRange(min, max) }}</span
    >
</template>
