import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { Alert, Box, Button, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Esqueci minha senha" />

            <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>
                Esqueceu sua senha?
            </Typography>

            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Sem problema. Informe seu e-mail e enviaremos um link para você escolher uma nova senha.
            </Typography>

            {status && (
                <Alert severity="success" sx={{ mb: 3 }}>
                    {status}
                </Alert>
            )}

            <Box component="form" onSubmit={submit}>
                <Stack spacing={2.5}>
                    <TextField
                        label="E-mail"
                        type="email"
                        value={data.email}
                        autoFocus
                        fullWidth
                        error={Boolean(errors.email)}
                        helperText={errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={processing}>
                            Enviar link de redefinição
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </GuestLayout>
    );
}
