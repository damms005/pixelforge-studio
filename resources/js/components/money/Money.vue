<script setup lang="ts">
import type { MoneyDecimals } from '@/lib/money';
import { exactUsd, formatUsd } from '@/lib/money';

withDefaults(
    defineProps<{
        amount: string | null | undefined;
        decimals?: MoneyDecimals;
    }>(),
    { decimals: 2 },
);
</script>

<template>
    <span
        class="tabular-nums"
        :title="amount == null ? undefined : `Exact: ${exactUsd(amount)}`"
        >{{ formatUsd(amount, decimals) }}</span
    >
</template>
