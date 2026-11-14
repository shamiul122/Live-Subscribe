<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    organization: {
        id: number;
        name: string;
        slug: string;
    };
}>();

const form = useForm({
    name: props.organization.name,
});

function submit(): void {
    form.patch(`/organizations/${props.organization.slug}`);
}
</script>

<template>
    <Head :title="`Edit ${props.organization.name}`" />

    <div class="mx-auto max-w-2xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold">Organization settings</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Update the organization name. The URL slug stays stable.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Organization name</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="form.processing" type="submit">
                    Save changes
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="`/organizations/${props.organization.slug}`">
                        Cancel
                    </Link>
                </Button>
            </div>
        </form>
    </div>
</template>
