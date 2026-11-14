<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { OrganizationSummary } from '@/types';

const props = defineProps<{
    organizations: OrganizationSummary[];
}>();

const page = usePage();

const currentOrganizationId = computed(
    () => page.props.currentOrganization?.id ?? null,
);

function switchOrganization(organization: OrganizationSummary): void {
    router.post(
        `/organizations/${organization.slug}/switch`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Organizations" />

    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Organizations</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Organizations you belong to. Switch the active organization
                    or create a new one.
                </p>
            </div>

            <Button as-child>
                <Link href="/organizations/create">New organization</Link>
            </Button>
        </div>

        <p
            v-if="props.organizations.length === 0"
            class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
        >
            You do not belong to any organization yet.
        </p>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <div
                v-for="organization in props.organizations"
                :key="organization.id"
                class="space-y-3 rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <Link
                            :href="`/organizations/${organization.slug}`"
                            class="font-medium hover:underline"
                        >
                            {{ organization.name }}
                        </Link>
                        <p class="text-sm text-muted-foreground">
                            {{ organization.members_count ?? 0 }} member(s)
                        </p>
                    </div>

                    <span
                        class="rounded-full border px-2 py-0.5 text-xs capitalize"
                    >
                        {{ organization.is_owner ? 'Owner' : organization.role }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button size="sm" variant="outline" as-child>
                        <Link :href="`/organizations/${organization.slug}`">
                            Open
                        </Link>
                    </Button>

                    <Button
                        v-if="organization.id !== currentOrganizationId"
                        size="sm"
                        variant="secondary"
                        @click="switchOrganization(organization)"
                    >
                        Set active
                    </Button>

                    <span
                        v-else
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Active organization
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
