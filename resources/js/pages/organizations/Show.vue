<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    OrganizationDetail,
    OrganizationInvitation,
    OrganizationMember,
    OrganizationPermissions,
    OrganizationRole,
} from '@/types';

const props = defineProps<{
    organization: OrganizationDetail;
    members: OrganizationMember[];
    invitations: OrganizationInvitation[];
    permissions: OrganizationPermissions;
    current_user_role: OrganizationRole | null;
    invitation_url: string | null;
}>();

const page = usePage();
const copied = ref(false);

const isOwner = computed(
    () => page.props.auth.user.id === props.organization.owner.id,
);

const inviteForm = useForm({
    email: '',
    role: 'member' as OrganizationRole,
});

const transferForm = useForm({
    email: '',
});

function sendInvitation(): void {
    inviteForm.post(`/organizations/${props.organization.slug}/invitations`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset('email');
            inviteForm.role = 'member';
        },
    });
}

function changeRole(member: OrganizationMember, role: string): void {
    if (!window.confirm(`Change ${member.user.name}'s role to ${role}?`)) {
        return;
    }

    router.patch(
        `/organizations/${props.organization.slug}/members/${member.id}`,
        { role },
        { preserveScroll: true },
    );
}

function removeMember(member: OrganizationMember): void {
    if (!window.confirm(`Remove ${member.user.name} from the organization?`)) {
        return;
    }

    router.delete(
        `/organizations/${props.organization.slug}/members/${member.id}`,
        { preserveScroll: true },
    );
}

function revokeInvitation(invitation: OrganizationInvitation): void {
    if (!window.confirm(`Revoke the invitation for ${invitation.email}?`)) {
        return;
    }

    router.delete(
        `/organizations/${props.organization.slug}/invitations/${invitation.id}`,
        { preserveScroll: true },
    );
}

function transferOwnership(): void {
    if (
        !window.confirm(
            `Transfer ownership to ${transferForm.email}? You will remain an admin member.`,
        )
    ) {
        return;
    }

    transferForm.post(
        `/organizations/${props.organization.slug}/transfer-ownership`,
    );
}

function leaveOrganization(): void {
    if (!window.confirm(`Leave ${props.organization.name}?`)) {
        return;
    }

    router.delete(`/organizations/${props.organization.slug}/leave`);
}

function deleteOrganization(): void {
    if (
        !window.confirm(
            `Delete ${props.organization.name}? This action soft deletes the organization and revokes pending invitations.`,
        )
    ) {
        return;
    }

    router.delete(`/organizations/${props.organization.slug}`);
}

async function copyInvitationUrl(): Promise<void> {
    if (!props.invitation_url) {
        return;
    }

    await navigator.clipboard.writeText(props.invitation_url);
    copied.value = true;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <Head :title="props.organization.name" />

    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold">
                        {{ props.organization.name }}
                    </h1>
                    <span
                        class="rounded-full border px-2 py-0.5 text-xs capitalize"
                    >
                        {{ isOwner ? 'Owner' : props.current_user_role }}
                    </span>
                </div>
                <p class="text-muted-foreground mt-1 text-sm">
                    Owned by {{ props.organization.owner.name }} ({{
                        props.organization.owner.email
                    }})
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="props.permissions.update"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="`/organizations/${props.organization.slug}/edit`"
                    >
                        Settings
                    </Link>
                </Button>
                <Button variant="ghost" as-child>
                    <Link href="/organizations">All organizations</Link>
                </Button>
            </div>
        </div>

        <p
            v-if="page.props.status"
            class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800"
        >
            {{ page.props.status }}
        </p>

        <div
            v-if="props.invitation_url"
            class="space-y-2 rounded-lg border border-blue-200 bg-blue-50 p-4"
        >
            <p class="text-sm font-medium text-blue-900">
                Invitation link (share it manually if email delivery is not
                configured)
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <Input :model-value="props.invitation_url" readonly />
                <Button size="sm" variant="outline" @click="copyInvitationUrl">
                    {{ copied ? 'Copied' : 'Copy' }}
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <section id="members" class="space-y-4 lg:col-span-2">
                <div class="rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="font-semibold">Members</h2>
                        <p class="text-muted-foreground text-sm">
                            Active and inactive memberships of this
                            organization.
                        </p>
                    </div>

                    <ul class="divide-y">
                        <li
                            v-for="member in props.members"
                            :key="member.id"
                            class="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ member.user.name }}
                                    <span
                                        v-if="member.is_owner"
                                        class="ms-1 rounded-full border px-2 py-0.5 text-xs"
                                    >
                                        Owner
                                    </span>
                                    <span
                                        v-if="member.status === 'inactive'"
                                        class="ms-1 rounded-full border px-2 py-0.5 text-xs"
                                    >
                                        Inactive
                                    </span>
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {{ member.user.email }}
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    Joined {{ formatDate(member.joined_at) }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <select
                                    v-if="
                                        props.permissions.update_role &&
                                        !member.is_owner &&
                                        member.status === 'active'
                                    "
                                    class="bg-background h-9 rounded-md border px-2 text-sm"
                                    :value="member.role"
                                    @change="
                                        changeRole(
                                            member,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="member">Member</option>
                                    <option value="admin">Admin</option>
                                </select>

                                <span v-else class="text-sm capitalize">
                                    {{ member.role_label }}
                                </span>

                                <Button
                                    v-if="
                                        props.permissions.remove_member &&
                                        !member.is_owner &&
                                        member.user.id !==
                                            page.props.auth.user.id
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="removeMember(member)"
                                >
                                    Remove
                                </Button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="props.permissions.invite"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div>
                        <h2 class="font-semibold">Invite a member</h2>
                        <p class="text-muted-foreground text-sm">
                            The invitation expires after seven days.
                        </p>
                    </div>

                    <form
                        class="flex flex-wrap items-end gap-3"
                        @submit.prevent="sendInvitation"
                    >
                        <div class="grid min-w-60 flex-1 gap-2">
                            <Label for="invite-email">Email address</Label>
                            <Input
                                id="invite-email"
                                v-model="inviteForm.email"
                                type="email"
                                required
                            />
                            <InputError :message="inviteForm.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="invite-role">Role</Label>
                            <select
                                id="invite-role"
                                v-model="inviteForm.role"
                                class="bg-background h-9 rounded-md border px-2 text-sm"
                            >
                                <option value="member">Member</option>
                                <option v-if="isOwner" value="admin">
                                    Admin
                                </option>
                            </select>
                            <InputError :message="inviteForm.errors.role" />
                        </div>

                        <Button :disabled="inviteForm.processing" type="submit">
                            Send invitation
                        </Button>
                    </form>
                </div>

                <div v-if="props.permissions.revoke" class="rounded-lg border">
                    <div class="border-b p-4">
                        <h2 class="font-semibold">Pending invitations</h2>
                        <p class="text-muted-foreground text-sm">
                            Invitations that have not been accepted yet.
                        </p>
                    </div>

                    <p
                        v-if="props.invitations.length === 0"
                        class="text-muted-foreground p-4 text-sm"
                    >
                        No pending invitations.
                    </p>

                    <ul v-else class="divide-y">
                        <li
                            v-for="invitation in props.invitations"
                            :key="invitation.id"
                            class="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ invitation.email }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {{ invitation.role_label }} · invited by
                                    {{ invitation.invited_by ?? 'unknown' }} ·
                                    expires
                                    {{ formatDate(invitation.expires_at) }}
                                    <span
                                        v-if="invitation.is_expired"
                                        class="font-medium"
                                    >
                                        (expired)
                                    </span>
                                </p>
                            </div>

                            <Button
                                size="sm"
                                variant="outline"
                                @click="revokeInvitation(invitation)"
                            >
                                Revoke
                            </Button>
                        </li>
                    </ul>
                </div>
            </section>

            <aside class="space-y-6">
                <div
                    v-if="props.permissions.transfer_ownership"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div>
                        <h2 class="font-semibold">Transfer ownership</h2>
                        <p class="text-muted-foreground text-sm">
                            Transfer ownership to an existing active member.
                        </p>
                    </div>

                    <form class="space-y-3" @submit.prevent="transferOwnership">
                        <div class="grid gap-2">
                            <Label for="transfer-email">Member email</Label>
                            <Input
                                id="transfer-email"
                                v-model="transferForm.email"
                                type="email"
                                required
                            />
                            <InputError :message="transferForm.errors.email" />
                        </div>
                        <Button
                            :disabled="transferForm.processing"
                            type="submit"
                            variant="secondary"
                        >
                            Transfer ownership
                        </Button>
                    </form>
                </div>

                <div class="space-y-4 rounded-lg border border-red-200 p-4">
                    <div>
                        <h2 class="font-semibold">Danger zone</h2>
                        <p class="text-muted-foreground text-sm">
                            These actions affect your access to the
                            organization.
                        </p>
                    </div>

                    <Button
                        v-if="props.permissions.leave"
                        variant="outline"
                        @click="leaveOrganization"
                    >
                        Leave organization
                    </Button>

                    <Button
                        v-if="props.permissions.delete"
                        variant="destructive"
                        @click="deleteOrganization"
                    >
                        Delete organization
                    </Button>
                </div>
            </aside>
        </div>
    </div>
</template>
