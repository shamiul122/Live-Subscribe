<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({
    name: '',
});

function submit(): void {
    form.post('/organizations');
}
</script>

<template>
    <Head title="New organization" />

    <div class="mx-auto max-w-2xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold">New organization</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                You will become the owner of the organization.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Organization name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    autocomplete="organization"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="flex items-center gap-3">
                <Button :disabled="form.processing" type="submit">
                    Create organization
                </Button>
                <Button variant="ghost" as-child>
                    <Link href="/organizations">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
