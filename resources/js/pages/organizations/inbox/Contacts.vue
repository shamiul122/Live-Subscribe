<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InboxContactSummary, InboxPermissions } from '@/types';

const props = defineProps<{
    contacts: InboxContactSummary[];
    permissions: InboxPermissions;
    organization: { id: number; name: string; slug: string };
}>();

const search = ref('');

const showForm = ref(false);
const editing = ref<InboxContactSummary | null>(null);

const name = ref('');
const email = ref('');
const phone = ref('');
const notes = ref('');

const filteredContacts = computed((): InboxContactSummary[] => {
    const needle = search.value.trim().toLowerCase();

    if (needle === '') {
        return props.contacts;
    }

    return props.contacts.filter((contact) => {
        const haystack = [
            contact.name,
            contact.email ?? '',
            contact.phone ?? '',
        ]
            .join(' ')
            .toLowerCase();

        return haystack.includes(needle);
    });
});

function startCreate(): void {
    editing.value = null;
    name.value = '';
    email.value = '';
    phone.value = '';
    notes.value = '';
    showForm.value = true;
}

function startEdit(contact: InboxContactSummary): void {
    editing.value = contact;
    name.value = contact.name;
    email.value = contact.email ?? '';
    phone.value = contact.phone ?? '';
    notes.value = contact.notes ?? '';
    showForm.value = true;
}

function closeForm(): void {
    showForm.value = false;
    editing.value = null;
}

function save(): void {
    const payload: Record<string, string> = {
        name: name.value,
        email: email.value,
        phone: phone.value,
        notes: notes.value,
    };

    if (editing.value !== null) {
        router.put(`/organizations/${props.organization.slug}/inbox/contacts/${editing.value.id}`, payload, { preserveScroll: true });
        closeForm();
        return;
    }

    router.post(`/organizations/${props.organization.slug}/inbox/contacts`, payload, { preserveScroll: true });
    closeForm();
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleDateString();
}
</script>

<template>
    <Head title="Contacts" />

    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Contacts</h1>

            <Button v-if="props.permissions.manage" size="sm" @click="startCreate">New contact</Button>
        </div>

        <p class="text-sm text-muted-foreground mb-6">
            Organization-scoped contact records for the unified inbox.
        </p>

        <div class="mb-6 max-w-sm">
            <Label for="contact-search">Search contacts</Label>
            <Input
                id="contact-search"
                v-model="search"
                placeholder="Name, email or phone"
            />
        </div>

        <div v-if="props.contacts.length === 0" class="rounded-lg border p-6 text-sm text-muted-foreground">
            No contacts found.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="contact in filteredContacts"
                :key="contact.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium">{{ contact.name }}</h3>
                        <p class="text-xs text-muted-foreground">{{ contact.email ?? 'No email' }}</p>
                        <p v-if="contact.phone" class="text-xs text-muted-foreground">{{ contact.phone }}</p>
                        <p v-if="contact.notes" class="text-xs text-muted-foreground">{{ contact.notes }}</p>
                    </div>

                    <Button
                        v-if="props.permissions.manage"
                        size="sm"
                        variant="outline"
                        @click="startEdit(contact)"
                    >
                        Edit
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="showForm" class="mt-6 rounded-lg border p-4">
            <h2 class="text-lg font-semibold mb-4">{{ editing ? 'Edit contact' : 'New contact' }}</h2>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="contact-name">Name</Label>
                    <Input id="contact-name" v-model="name" />
                </div>

                <div class="grid gap-2">
                    <Label for="contact-email">Email</Label>
                    <Input id="contact-email" v-model="email" />
                </div>

                <div class="grid gap-2">
                    <Label for="contact-phone">Phone</Label>
                    <Input id="contact-phone" v-model="phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="contact-notes">Notes</Label>
                    <Input id="contact-notes" v-model="notes" />
                </div>

                <div class="flex gap-2">
                    <Button size="sm" @click="save">Save</Button>
                    <Button size="sm" variant="outline" @click="closeForm">Cancel</Button>
                </div>
            </div>
        </div>
    </div>
</template>
