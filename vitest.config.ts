import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Tests de componentes Vue (sin el plugin de Laravel: no hacen falta manifest ni servidor).
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'happy-dom',
        include: ['tests/js/**/*.test.ts'],
        restoreMocks: true,
    },
});
