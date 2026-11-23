<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, FolderGit2, Receipt, Tags } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const REPOSITORY_URL = 'https://github.com/damms005/pixelforge-studio';

const points = [
    {
        icon: Tags,
        title: 'Price range before you submit',
        text: 'Size and quality fix the output tokens. Prompt and reasoning tokens are only known later, so you see the lowest and highest price first.',
    },
    {
        icon: Receipt,
        title: 'Hold, then the exact charge',
        text: 'The highest price is held while the image is made. When it finishes, you pay the exact cost and the rest comes back.',
    },
    {
        icon: CircleCheck,
        title: 'Failed images cost nothing',
        text: 'If Pixelforge rejects or fails a generation, the hold is released in full.',
    },
];
</script>

<template>
    <Head title="Image generation with exact per-token billing" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-5">
            <div class="flex items-center gap-2">
                <div class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <AppLogoIcon class="size-5" />
                </div>
                <span class="font-semibold">{{ $page.props.name }}</span>
            </div>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="dashboard()">Open dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Register</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-12 px-6 py-12">
            <section class="max-w-2xl">
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    Generate images on the Pixelforge API and pay the exact cost
                </h1>
                <p class="mt-4 text-lg text-muted-foreground">
                    Write a prompt, pick a size and quality, and see your images as they finish. Each image costs
                    what Pixelforge charges for its tokens, plus 25%.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Button v-if="$page.props.auth.user" size="lg" as-child>
                        <Link :href="dashboard()">Open dashboard</Link>
                    </Button>
                    <template v-else>
                        <Button size="lg" as-child>
                            <Link :href="register()">Create an account</Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                    </template>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="point in points"
                    :key="point.title"
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <component :is="point.icon" class="size-5 text-muted-foreground" />
                    <h2 class="mt-3 font-medium">{{ point.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ point.text }}</p>
                </article>
            </section>
        </main>

        <footer class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-6 text-sm text-muted-foreground">
            <span>Credits are US dollars. Purchases on this demo are not charged.</span>
            <a :href="REPOSITORY_URL" class="inline-flex items-center gap-1.5 hover:text-foreground" target="_blank" rel="noopener">
                <FolderGit2 class="size-4" /> Source code
            </a>
        </footer>
    </div>
</template>
