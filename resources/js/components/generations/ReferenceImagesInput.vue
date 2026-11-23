<script setup lang="ts">
import { Plus, X as RemoveIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{ max: number }>();

const urls = defineModel<string[]>({ required: true });

function add() {
    if (urls.value.length < props.max) {
        urls.value = [...urls.value, ''];
    }
}

function update(position: number, url: string) {
    urls.value = urls.value.map((current, i) => (i === position ? url : current));
}

function remove(position: number) {
    urls.value = urls.value.filter((_, i) => i !== position);
}
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between">
            <Label>Reference images <span class="font-normal text-muted-foreground">(optional)</span></Label>
            <Button type="button" variant="ghost" size="sm" :disabled="urls.length >= max" @click="add">
                <Plus class="size-4" /> Add URL
            </Button>
        </div>
        <div v-for="(url, position) in urls" :key="position" class="flex gap-2">
            <input
                :value="url"
                type="url"
                placeholder="https://example.com/style.png"
                class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                @input="update(position, ($event.target as HTMLInputElement).value)"
            />
            <button
                type="button"
                aria-label="Remove URL"
                class="inline-flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                @click="remove(position)"
            >
                <RemoveIcon class="size-4" />
            </button>
        </div>
        <p v-if="urls.length === 0" class="text-xs text-muted-foreground">
            Up to {{ max }} image URLs to guide the style. Each adds 765 input image tokens.
        </p>
    </div>
</template>
