<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    name: 'LoginPage',
    layout: {
        title: 'Connexion à votre atelier',
        description:
            'Utilisez votre identifiant et votre mot de passe précréés.',
    },
});
</script>

<template>
    <div>
        <Head title="Connexion" />
        <Form
            v-slot="{ errors, processing }"
            v-bind="store.form()"
            :reset-on-success="['password']"
            :reset-on-error="['password']"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-2">
                <Label for="login">Identifiant</Label>
                <Input
                    id="login"
                    v-focus
                    name="login"
                    required
                    maxlength="190"
                    autocomplete="username"
                />
                <InputError :message="errors.login" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mot de passe</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />
                <InputError :message="errors.password" />
            </div>
            <Button
                type="submit"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Se connecter
            </Button>
        </Form>
    </div>
</template>
