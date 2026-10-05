import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css',
                    'resources/css/dashboard.css',
                    'resources/js/app.js',
                    'resources/css/dashboard-d.css',
                    'resources/css/manage-payroll.css',
                    'resources/css/show-payroll.css',
                    'resources/css/edit-payroll.css',
                    'resources/css/edit-time-payroll.css',
                    'resources/css/payroll-history.css',
                    'resources/css/history-index.css'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        }, 
    },
});
