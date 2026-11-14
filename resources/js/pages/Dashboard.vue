<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Welcome back. Manage your organizations and memberships from
                here.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <Link
                href="/organizations"
                class="rounded-lg border p-4 transition hover:bg-muted"
            >
                <p class="font-medium">Organizations</p>
                <p class="text-sm text-muted-foreground">
                    View, create and manage the organizations you belong to.
                </p>
            </Link>

            <Link
                href="/settings/profile"
                class="rounded-lg border p-4 transition hover:bg-muted"
            >
                <p class="font-medium">Account settings</p>
                <p class="text-sm text-muted-foreground">
                    Update your profile and security settings.
                </p>
            </Link>
        </div>
    </div>
</template>
