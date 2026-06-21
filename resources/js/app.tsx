import '../css/app.css';
import './bootstrap';

import AppThemeProvider from '@/Contexts/ThemeContext';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx'));
        const PageComponent = (page as { default: ComponentType<Record<string, unknown>> }).default;

        // Não muta `page.default`: módulos ES dinâmicos vêm congelados em build de produção.
        return {
            default: (props: Record<string, unknown>) => (
                <AppThemeProvider>
                    <PageComponent {...props} />
                </AppThemeProvider>
            ),
        };
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4F46E5',
    },
});
