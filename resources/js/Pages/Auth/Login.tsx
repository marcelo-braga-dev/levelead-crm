import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Alert, Box, Button, Checkbox, FormControlLabel, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Entrar" />

            <Typography variant="h6" sx={{ mb: 3, fontWeight: 700 }}>
                Entrar
            </Typography>

            {status && (
                <Alert severity="success" sx={{ mb: 3 }}>
                    {status}
                </Alert>
            )}

            <Box component="form" onSubmit={submit}>
                <Stack spacing={2.5}>
                    <TextField
                        id="email"
                        label="E-mail"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        fullWidth
                        error={Boolean(errors.email)}
                        helperText={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <TextField
                        id="password"
                        label="Senha"
                        type="password"
                        value={data.password}
                        autoComplete="current-password"
                        fullWidth
                        error={Boolean(errors.password)}
                        helperText={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                        }
                        label="Lembrar de mim"
                    />

                    <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                        {canResetPassword ? (
                            <Link href={route('password.request')}>Esqueceu sua senha?</Link>
                        ) : (
                            <span />
                        )}

                        <Button type="submit" variant="contained" disabled={processing}>
                            Entrar
                        </Button>
                    </Stack>
                </Stack>
            </Box>
        </GuestLayout>
    );
}
