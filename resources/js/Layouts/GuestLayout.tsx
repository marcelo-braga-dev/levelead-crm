import { Box, Paper, Stack, Typography } from '@mui/material';
import { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <Box
            sx={{
                minHeight: '100vh',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                bgcolor: 'background.default',
                px: 2,
            }}
        >
            <Stack spacing={3} sx={{ width: '100%', maxWidth: 420, alignItems: 'center' }}>
                <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                    <Box
                        sx={{
                            width: 40,
                            height: 40,
                            borderRadius: 2,
                            bgcolor: 'primary.main',
                            color: 'primary.contrastText',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontWeight: 700,
                            fontSize: '1.1rem',
                        }}
                    >
                        L
                    </Box>
                    <Typography variant="h6" sx={{ fontWeight: 700 }}>
                        LeveLead CRM
                    </Typography>
                </Stack>

                <Paper sx={{ p: 4, width: '100%' }}>{children}</Paper>
            </Stack>
        </Box>
    );
}
