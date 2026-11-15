<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BarChart3,
    BookOpen,
    Building2,
    CreditCard,
    FileText,
    FolderGit2,
    Inbox,
    KeyRound,
    LayoutGrid,
    Megaphone,
    Receipt,
    Settings,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const currentOrganization = computed(() => page.props.currentOrganization);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Organizations',
        href: '/organizations',
        icon: Building2,
    },
];

type NavGroup = {
    label: string;
    items: NavItem[];
};

const organizationNavGroups = computed<NavGroup[]>(() => {
    const organization = currentOrganization.value;

    if (!organization) {
        return [];
    }

    const base = `/organizations/${organization.slug}`;

    return [
        {
            label: 'Workspace',
            items: [
                { title: 'Overview', href: dashboard(), icon: LayoutGrid },
                { title: 'Inbox', href: `${base}/inbox`, icon: Inbox },
                {
                    title: 'Contacts',
                    href: `${base}/inbox/contacts`,
                    icon: Users,
                },
                {
                    title: 'Connected Accounts',
                    href: `${base}/connected-accounts`,
                    icon: Building2,
                },
            ],
        },
        {
            label: 'Marketing',
            items: [
                {
                    title: 'Campaigns',
                    href: `${base}/campaigns`,
                    icon: Megaphone,
                },
            ],
        },
        {
            label: 'Communication',
            items: [
                {
                    title: 'Message Templates',
                    href: `${base}/message-templates`,
                    icon: FileText,
                },
            ],
        },
        {
            label: 'Organization',
            items: [
                { title: 'Members', href: `${base}#members`, icon: Users },
                { title: 'Settings', href: `${base}/edit`, icon: Settings },
            ],
        },
        {
            label: 'Billing',
            items: [
                { title: 'Plans', href: '/plans', icon: CreditCard },
                {
                    title: 'Subscription',
                    href: `${base}/subscription`,
                    icon: Receipt,
                },
                {
                    title: 'Licenses',
                    href: `${base}/licenses`,
                    icon: KeyRound,
                },
                { title: 'Usage', href: `${base}/usage`, icon: BarChart3 },
            ],
        },
    ];
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Platform" />

            <NavMain
                v-for="group in organizationNavGroups"
                :key="group.label"
                :items="group.items"
                :label="group.label"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
