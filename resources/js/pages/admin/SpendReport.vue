<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatUsd } from '@/lib/money';
import { report } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spend report', href: report() }],
    },
});

type UserRow = {
    user_id: number;
    name: string;
    email: string;
    generations: number;
    charged: string;
    provider_cost: string;
    profit: string;
};

type Day = {
    date: string;
    generations: number;
    charged: string;
    provider_cost: string;
    profit: string;
    pixelforge_cost: string | null;
    matches_pixelforge: boolean | null;
    users: UserRow[];
};

const props = defineProps<{
    start: string;
    end: string;
    days: Day[];
    totals: { generations: number; charged: string; provider_cost: string; profit: string; pixelforge_cost: string | null };
    pixelforge_error: string | null;
}>();

const range = ref({ start: props.start, end: props.end });

function apply() {
    router.get(report.url(), range.value, { preserveState: true });
}
</script>

<template>
    <Head title="Spend report" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Spend report</h1>
                <p class="text-sm text-muted-foreground">
                    Per UTC day, by the day each generation completed (Pixelforge's billing day). Provider cost is checked
                    against <code>GET /v1/usage</code> for this app's API key.
                </p>
            </div>
            <form class="flex items-end gap-2" @submit.prevent="apply">
                <div class="grid gap-1">
                    <Label for="start" class="text-xs">From</Label>
                    <Input id="start" v-model="range.start" type="date" />
                </div>
                <div class="grid gap-1">
                    <Label for="end" class="text-xs">To</Label>
                    <Input id="end" v-model="range.end" type="date" />
                </div>
                <Button type="submit" variant="outline">Show</Button>
            </form>
        </div>

        <p v-if="pixelforge_error" class="rounded-md bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
            Could not load Pixelforge usage: {{ pixelforge_error }}
        </p>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border p-4">
                <div class="text-xs text-muted-foreground">Credit spend (charged to users)</div>
                <div class="text-xl font-semibold tabular-nums">{{ formatUsd(totals.charged) }}</div>
            </div>
            <div class="rounded-xl border p-4">
                <div class="text-xs text-muted-foreground">Provider cost (our records)</div>
                <div class="text-xl font-semibold tabular-nums">{{ formatUsd(totals.provider_cost) }}</div>
            </div>
            <div class="rounded-xl border p-4">
                <div class="text-xs text-muted-foreground">Pixelforge reports</div>
                <div class="text-xl font-semibold tabular-nums">{{ formatUsd(totals.pixelforge_cost) }}</div>
            </div>
            <div class="rounded-xl border p-4">
                <div class="text-xs text-muted-foreground">Profit</div>
                <div class="text-xl font-semibold tabular-nums">{{ formatUsd(totals.profit) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Day / user</th>
                        <th class="px-4 py-2 text-right font-medium">Images</th>
                        <th class="px-4 py-2 text-right font-medium">Credit spend</th>
                        <th class="px-4 py-2 text-right font-medium">Provider cost</th>
                        <th class="px-4 py-2 text-right font-medium">Pixelforge reports</th>
                        <th class="px-4 py-2 text-right font-medium">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="day in days" :key="day.date">
                        <tr class="border-t font-medium">
                            <td class="px-4 py-2">{{ day.date }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ day.generations }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ formatUsd(day.charged) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ formatUsd(day.provider_cost) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                <span class="inline-flex items-center gap-1">
                                    {{ formatUsd(day.pixelforge_cost) }}
                                    <Check v-if="day.matches_pixelforge === true" class="size-4 text-emerald-600" aria-label="Matches" />
                                    <TriangleAlert
                                        v-else-if="day.matches_pixelforge === false"
                                        class="size-4 text-amber-600"
                                        aria-label="Does not match our records"
                                    />
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ formatUsd(day.profit) }}</td>
                        </tr>
                        <tr v-for="user in day.users" :key="`${day.date}-${user.user_id}`" class="text-muted-foreground">
                            <td class="py-1.5 pr-4 pl-8">{{ user.name }} <span class="text-xs">({{ user.email }})</span></td>
                            <td class="px-4 py-1.5 text-right tabular-nums">{{ user.generations }}</td>
                            <td class="px-4 py-1.5 text-right tabular-nums">{{ formatUsd(user.charged) }}</td>
                            <td class="px-4 py-1.5 text-right tabular-nums">{{ formatUsd(user.provider_cost) }}</td>
                            <td class="px-4 py-1.5" />
                            <td class="px-4 py-1.5 text-right tabular-nums">{{ formatUsd(user.profit) }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
