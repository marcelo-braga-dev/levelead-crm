import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { Box, Button, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar senha" />

            <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>
                Confirmar senha
            </Typography>

            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Esta é uma área segura da aplicação. Confirme sua senha antes de continuar.
            </Typography>

            <Box component="form" onSubmit={submit}>
                <Stack spacing={2.5}>
                    <TextField
                        label="Senha"
                        type="password"
                        value={data.password}
                        autoFocus
                        fullWidth
                        error={Boolean(errors.password)}
                        helperText={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={processing}>
                            Confirmar
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </GuestLayout>
    );
}
