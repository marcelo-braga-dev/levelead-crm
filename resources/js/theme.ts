import { createTheme, type Theme } from '@mui/material/styles';

export function createAppTheme(mode: 'light' | 'dark', primary: string, secondary: string): Theme {
    return createTheme({
        palette: {
            mode,
            primary: { main: primary },
            secondary: { main: secondary },
            background:
                mode === 'light'
                    ? { default: '#F7F8FB', paper: '#FFFFFF' }
                    : { default: '#0F1117', paper: '#171a23' },
        },
        shape: {
            borderRadius: 10,
        },
        typography: {
            fontFamily: 'Figtree, sans-serif',
            h1: { fontWeight: 700 },
            h2: { fontWeight: 700 },
            h3: { fontWeight: 700 },
            h4: { fontWeight: 600 },
            h5: { fontWeight: 600 },
            h6: { fontWeight: 600 },
        },
        components: {
            MuiButton: {
                defaultProps: { disableElevation: true },
                styleOverrides: {
                    root: { borderRadius: 8, textTransform: 'none', fontWeight: 600 },
                },
            },
            MuiAppBar: {
                styleOverrides: {
                    root: {
                        boxShadow: 'none',
                        borderBottom: `1px solid ${mode === 'light' ? '#E5E7EB' : '#262A35'}`,
                    },
                },
            },
            MuiDrawer: {
                styleOverrides: {
                    paper: {
                        boxShadow: 'none',
                        borderRight: `1px solid ${mode === 'light' ? '#E5E7EB' : '#262A35'}`,
                    },
                },
            },
            MuiPaper: {
                styleOverrides: {
                    root: { backgroundImage: 'none' },
                },
            },
            MuiCard: {
                styleOverrides: {
                    root: {
                        borderRadius: 12,
                        border: `1px solid ${mode === 'light' ? '#E5E7EB' : '#262A35'}`,
                    },
                },
            },
            MuiTableContainer: {
                styleOverrides: {
                    root: {
                        borderRadius: 12,
                        border: `1px solid ${mode === 'light' ? '#E5E7EB' : '#262A35'}`,
                    },
                },
            },
            MuiChip: {
                styleOverrides: {
                    root: { fontWeight: 600 },
                },
            },
        },
    });
}
