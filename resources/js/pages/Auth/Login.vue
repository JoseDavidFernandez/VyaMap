<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <main>
        <h1>Login</h1>

        <form @submit.prevent="submit">
            <div>
                <label for="email">Email</label>

                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                />

                <p v-if="form.errors.email">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password">Password</label>

                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                />

                <p v-if="form.errors.password">
                    {{ form.errors.password }}
                </p>
            </div>

            <label>
                <input
                    v-model="form.remember"
                    type="checkbox"
                />

                Remember me
            </label>

            <button
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Logging in...' : 'Login' }}
            </button>
        </form>
    </main>
</template>