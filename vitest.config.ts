import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// React strips `act` from production builds; testing
// needs the development build regardless of the
// ambient NODE_ENV.
process.env.NODE_ENV = 'test';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
