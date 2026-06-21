import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Box, Button, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Cadastro" />

            <Typography variant="h6" sx={{ mb: 3, fontWeight: 700 }}>
                Criar conta
            </Typography>

            <Box component="form" onSubmit={submit}>
                <Stack spacing={2.5}>
                    <TextField
                        label="Nome"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        fullWidth
                        required
                        error={Boolean(errors.name)}
                        helperText={errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />

                    <TextField
                        label="E-mail"
                        type="email"
                        value={data.email}
                        autoComplete="username"
                        fullWidth
                        required
                        error={Boolean(errors.email)}
                        helperText={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <TextField
                        label="Senha"
                        type="password"
                        value={data.password}
                        autoComplete="new-password"
                        fullWidth
                        required
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
                        required
                        error={Boolean(errors.password_confirmation)}
                        helperText={errors.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />

                    <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                        <Link href={route('login')}>Já tem conta?</Link>

                        <Button type="submit" variant="contained" disabled={processing}>
                            Cadastrar
                        </Button>
                    </Stack>
                </Stack>
            </Box>
        </GuestLayout>
    );
}
