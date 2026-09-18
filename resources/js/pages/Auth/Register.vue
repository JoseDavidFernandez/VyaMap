<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register');
};
</script>

<template>
    <main>
        <h1>Create account</h1>

        <form @submit.prevent="submit">
            <div>
                <label for="name">Name</label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    required
                />

                <p v-if="form.errors.name">
                    {{ form.errors.name }}
                </p>
            </div>

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
                    autocomplete="new-password"
                    required
                />

                <p v-if="form.errors.password">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation">
                    Confirm password
                </label>

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Creating account...' : 'Create account' }}
            </button>
        </form>
    </main>
</template>