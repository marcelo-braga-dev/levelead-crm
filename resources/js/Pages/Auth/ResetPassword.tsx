import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { Box, Button, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Redefinir senha" />

            <Typography variant="h6" sx={{ mb: 3, fontWeight: 700 }}>
                Redefinir senha
            </Typography>

            <Box component="form" onSubmit={submit}>
                <Stack spacing={2.5}>
                    <TextField
                        label="E-mail"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        fullWidth
                        error={Boolean(errors.email)}
                        helperText={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <TextField
                        label="Senha"
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        fullWidth
                        error={Boolean(errors.password)}
                        helperText={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <TextField
                        label="Confirmar senha"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        fullWidth
                        error={Boolean(errors.password_confirmation)}
                        helperText={errors.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={processing}>
                            Redefinir senha
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </GuestLayout>
    );
}
