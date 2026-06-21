import { PageProps } from '@/types';
import { createAppTheme } from '@/theme';
import { usePage } from '@inertiajs/react';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider } from '@mui/material/styles';
import { createContext, PropsWithChildren, useContext, useMemo, useState } from 'react';

type ColorMode = 'light' | 'dark';

const STORAGE_KEY = 'color-mode';

export const ColorModeContext = createContext<{ mode: ColorMode; toggleMode: () => void }>({
    mode: 'light',
    toggleMode: () => {},
});

export function useColorMode() {
    return useContext(ColorModeContext);
}

function initialMode(): ColorMode {
    const stored = window.localStorage.getItem(STORAGE_KEY);

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export default function AppThemeProvider({ children }: PropsWithChildren) {
    const { theme } = usePage<PageProps>().props;
    const [mode, setMode] = useState<ColorMode>(initialMode);

    const colorMode = useMemo(
        () => ({
            mode,
            toggleMode: () => {
                setMode((current) => {
                    const next = current === 'light' ? 'dark' : 'light';
                    window.localStorage.setItem(STORAGE_KEY, next);

                    return next;
                });
            },
        }),
        [mode],
    );

    const muiTheme = useMemo(
        () => createAppTheme(mode, theme.primary, theme.secondary),
        [mode, theme.primary, theme.secondary],
    );

    return (
        <ColorModeContext.Provider value={colorMode}>
            <ThemeProvider theme={muiTheme}>
                <CssBaseline />
                {children}
            </ThemeProvider>
        </ColorModeContext.Provider>
    );
}
