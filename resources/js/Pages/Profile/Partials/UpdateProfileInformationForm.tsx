import { PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Alert, Box, Button, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage<PageProps>().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <Box>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
                Informações do perfil
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Atualize seu nome e endereço de e-mail.
            </Typography>

            <Box component="form" onSubmit={submit}>
                <Stack spacing={2.5} sx={{ maxWidth: 480 }}>
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

                    {mustVerifyEmail && user.email_verified_at === null && (
                        <Box>
                            <Typography variant="body2">
                                Seu endereço de e-mail não está verificado.{' '}
                                <Link href={route('verification.send')} method="post" as="button">
                                    Clique aqui para reenviar o e-mail de verificação.
                                </Link>
                            </Typography>

                            {status === 'verification-link-sent' && (
                                <Alert severity="success" sx={{ mt: 1 }}>
                                    Um novo link de verificação foi enviado para seu e-mail.
                                </Alert>
                            )}
                        </Box>
                    )}

                    <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                        <Button type="submit" variant="contained" disabled={processing}>
                            Salvar
                        </Button>

                        {recentlySuccessful && (
                            <Typography variant="body2" color="text.secondary">
                                Salvo.
                            </Typography>
                        )}
                    </Stack>
                </Stack>
            </Box>
        </Box>
    );
}
