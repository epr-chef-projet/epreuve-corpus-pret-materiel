<script setup lang="ts">
defineOptions({ name: 'AccessLoginPage' });
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { nextTick, onScopeDispose } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { LoadingButton } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { useAccessStore } from '@/modules/identity-access/public/access';
import { fieldError } from '@/shared/i18n/fieldError';
import { store } from '@/routes/login';

const composer = useI18n();
const { t } = composer;
const page = usePage();
const form = useForm({ login: '', password: '' });
const access = useAccessStore();
const release = access.own(() => form.resetAndClearErrors());
onScopeDispose(release);
const error = (field: 'login' | 'password') =>
    fieldError(
        page.props.validationPresentation,
        field,
        form.errors[field],
        composer,
    );
function submit() {
    const generation = access.generation;
    form.post(store.url(), {
        onSuccess: () => form.reset('password'),
        onError: () => {
            form.reset('password');
            void nextTick(() => {
                if (generation !== access.generation) return;
                const field = form.errors.login ? 'login' : 'password';
                document.getElementById(field)?.focus();
            });
        },
    });
}
</script>

<template>
    <div>
        <Head :title="t('identity.signIn')" />
        <p v-if="access.status === 'offline'" role="status">
            {{ t('common.offline') }}
        </p>
        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="login">{{ t('identity.login') }}</Label>
                <Input
                    id="login"
                    v-model="form.login"
                    v-focus
                    name="login"
                    required
                    maxlength="190"
                    autocomplete="username"
                    aria-describedby="login-error"
                    :aria-invalid="Boolean(form.errors.login)"
                />
                <InputError id="login-error" :message="error('login')" />
            </div>
            <div class="grid gap-2">
                <Label for="password">{{ t('identity.password') }}</Label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    name="password"
                    required
                    autocomplete="current-password"
                    aria-describedby="password-error"
                    :aria-invalid="Boolean(form.errors.password)"
                />
                <InputError id="password-error" :message="error('password')" />
            </div>
            <LoadingButton
                type="submit"
                :label="t('identity.signIn')"
                :busy-label="t('identity.signingIn')"
                :busy="form.processing"
                :disabled="form.processing || access.status === 'offline'"
                data-test="login-button"
            />
        </form>
    </div>
</template>
