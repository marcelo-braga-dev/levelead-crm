import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Alert, Box, Button, Stack, Typography } from '@mui/material';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificação de e-mail" />

            <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>
                Verifique seu e-mail
            </Typography>

            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Obrigado por se cadastrar! Antes de começar, confirme seu endereço de e-mail clicando no
                link que acabamos de enviar. Se não recebeu o e-mail, ficaremos felizes em enviar outro.
            </Typography>

            {status === 'verification-link-sent' && (
                <Alert severity="success" sx={{ mb: 3 }}>
                    Um novo link de verificação foi enviado para o e-mail informado no cadastro.
                </Alert>
            )}

            <Box component="form" onSubmit={submit}>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button type="submit" variant="contained" disabled={processing}>
                        Reenviar e-mail de verificação
                    </Button>

                    <Link href={route('logout')} method="post" as="button">
                        Sair
                    </Link>
                </Stack>
            </Box>
        </GuestLayout>
    );
}
