import { alpha, createTheme, type Theme } from '@mui/material/styles';

export function createAppTheme(mode: 'light' | 'dark', primary: string, secondary: string): Theme {
    const isLight = mode === 'light';
    const divider = isLight ? '#E5E7EB' : '#262A35';

    return createTheme({
        palette: {
            mode,
            primary: { main: primary },
            secondary: { main: secondary },
            background: isLight
                ? { default: '#F4F6FB', paper: '#FFFFFF' }
                : { default: '#0B0D14', paper: '#151823' },
            divider,
        },
        shape: {
            borderRadius: 12,
        },
        typography: {
            fontFamily: 'Figtree, sans-serif',
            h1: { fontWeight: 700 },
            h2: { fontWeight: 700 },
            h3: { fontWeight: 700 },
            h4: { fontWeight: 700 },
            h5: { fontWeight: 600 },
            h6: { fontWeight: 700 },
            subtitle1: { fontWeight: 600 },
            subtitle2: { fontWeight: 600 },
        },
        components: {
            MuiCssBaseline: {
                styleOverrides: {
                    '*': {
                        scrollbarWidth: 'thin',
                        scrollbarColor: `${alpha(primary, 0.4)} transparent`,
                    },
                    '*::-webkit-scrollbar': { width: 8, height: 8 },
                    '*::-webkit-scrollbar-thumb': {
                        backgroundColor: alpha(primary, 0.35),
                        borderRadius: 8,
                    },
                    '*::-webkit-scrollbar-track': { backgroundColor: 'transparent' },
                },
            },
            MuiButton: {
                defaultProps: { disableElevation: true },
                styleOverrides: {
                    root: { borderRadius: 9, textTransform: 'none', fontWeight: 600 },
                    contained: {
                        boxShadow: `0 6px 16px -6px ${alpha(primary, 0.55)}`,
                        transition: 'transform 0.15s ease, box-shadow 0.15s ease',
                        '&:hover': {
                            boxShadow: `0 10px 22px -6px ${alpha(primary, 0.6)}`,
                            transform: 'translateY(-1px)',
                        },
                    },
                },
            },
            MuiAppBar: {
                styleOverrides: {
                    root: { boxShadow: 'none' },
                },
            },
            MuiDrawer: {
                styleOverrides: {
                    paper: {
                        boxShadow: 'none',
                        borderRight: `1px solid ${divider}`,
                        backgroundImage: isLight
                            ? 'none'
                            : `linear-gradient(180deg, ${alpha(primary, 0.06)} 0%, transparent 30%)`,
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
                        borderRadius: 14,
                        border: `1px solid ${divider}`,
                        transition: 'transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease',
                    },
                },
            },
            MuiTableContainer: {
                styleOverrides: {
                    root: {
                        borderRadius: 14,
                        border: `1px solid ${divider}`,
                    },
                },
            },
            MuiTableHead: {
                styleOverrides: {
                    root: {
                        '& .MuiTableCell-root': {
                            fontWeight: 700,
                            backgroundColor: isLight ? alpha(primary, 0.05) : alpha(primary, 0.1),
                        },
                    },
                },
            },
            MuiChip: {
                styleOverrides: {
                    root: { fontWeight: 600 },
                },
            },
            MuiListItemButton: {
                styleOverrides: {
                    root: {
                        borderRadius: 10,
                        transition: 'background-color 0.15s ease, color 0.15s ease',
                        '&.Mui-selected': {
                            backgroundColor: alpha(primary, isLight ? 0.1 : 0.18),
                            color: primary,
                            '& .MuiListItemIcon-root': { color: primary },
                            '&:hover': { backgroundColor: alpha(primary, isLight ? 0.14 : 0.24) },
                        },
                    },
                },
            },
        },
    });
}
