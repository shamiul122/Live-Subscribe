<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BarChart3,
    CreditCard,
    FileText,
    Inbox,
    KeyRound,
    Megaphone,
    Receipt,
    Settings,
    Users,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { OrganizationSummary } from '@/types';

const page = usePage();

const currentOrganization = computed<OrganizationSummary | null>(
    () => page.props.currentOrganization ?? null,
);

type ModuleCard = {
    title: string;
    description: string;
    href: string;
    icon: typeof Inbox;
};

type ModuleGroup = {
    label: string;
    modules: ModuleCard[];
};

const moduleGroups = computed<ModuleGroup[]>(() => {
    const organization = currentOrganization.value;

    if (!organization) {
        return [];
    }

    const base = `/organizations/${organization.slug}`;

    return [
        {
            label: 'Workspace',
            modules: [
                {
                    title: 'Inbox',
                    description: 'Read and reply to conversations.',
                    href: `${base}/inbox`,
                    icon: Inbox,
                },
                {
                    title: 'Contacts',
                    description: 'Manage the people you talk to.',
                    href: `${base}/inbox/contacts`,
                    icon: Users,
                },
                {
                    title: 'Connected Accounts',
                    description: 'Channels linked to this organization.',
                    href: `${base}/connected-accounts`,
                    icon: Settings,
                },
            ],
        },
        {
            label: 'Marketing',
            modules: [
                {
                    title: 'Campaigns',
                    description: 'Plan and prepare marketing campaigns.',
                    href: `${base}/campaigns`,
                    icon: Megaphone,
                },
            ],
        },
        {
            label: 'Communication',
            modules: [
                {
                    title: 'Message Templates',
                    description: 'Reusable, variable-aware message content.',
                    href: `${base}/message-templates`,
                    icon: FileText,
                },
            ],
        },
        {
            label: 'Organization',
            modules: [
                {
                    title: 'Members',
                    description: 'Invite and manage your team.',
                    href: `${base}#members`,
                    icon: Users,
                },
                {
                    title: 'Settings',
                    description: 'Update organization details.',
                    href: `${base}/edit`,
                    icon: Settings,
                },
            ],
        },
        {
            label: 'Billing',
            modules: [
                {
                    title: 'Plans',
                    description: 'Compare available plans.',
                    href: '/plans',
                    icon: CreditCard,
                },
                {
                    title: 'Subscription',
                    description: 'Manage your plan and trial.',
                    href: `${base}/subscription`,
                    icon: Receipt,
                },
                {
                    title: 'Licenses',
                    description: 'Issue and rotate license keys.',
                    href: `${base}/licenses`,
                    icon: KeyRound,
                },
                {
                    title: 'Usage',
                    description: 'Track feature usage and limits.',
                    href: `${base}/usage`,
                    icon: BarChart3,
                },
            ],
        },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-8 p-6">
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p
                v-if="currentOrganization"
                class="text-muted-foreground mt-1 text-sm"
            >
                Everything for
                <span class="text-foreground font-medium">{{
                    currentOrganization.name
                }}</span>
                in one place.
            </p>
            <p v-else class="text-muted-foreground mt-1 text-sm">
                Select or create an organization to unlock the workspace
                modules.
            </p>
        </div>

        <div v-if="!currentOrganization" class="rounded-lg border p-6 text-sm">
            <p class="font-medium">No active organization yet</p>
            <p class="text-muted-foreground mt-1">
                Create an organization or accept an invitation to start using
                the inbox, campaigns, message templates and billing.
            </p>
            <Link href="/organizations" class="mt-4 inline-block">
                <Button size="sm">Go to organizations</Button>
            </Link>
        </div>

        <section
            v-for="group in moduleGroups"
            :key="group.label"
            class="space-y-3"
        >
            <h2
                class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
            >
                {{ group.label }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="module in group.modules"
                    :key="module.title"
                    :href="module.href"
                    class="hover:bg-muted/50 flex items-start gap-3 rounded-lg border p-4 transition"
                >
                    <component
                        :is="module.icon"
                        class="text-muted-foreground mt-0.5 size-5 shrink-0"
                    />
                    <span>
                        <span class="block font-medium">{{
                            module.title
                        }}</span>
                        <span
                            class="text-muted-foreground mt-1 block text-sm"
                            >{{ module.description }}</span
                        >
                    </span>
                </Link>
            </div>
        </section>
    </div>
</template>
