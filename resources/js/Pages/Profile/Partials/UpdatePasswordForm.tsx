import { useForm } from '@inertiajs/react';
import { Box, Button, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                if (formErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (formErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <Box>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
                Atualizar senha
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Use uma senha longa e aleatória para manter sua conta segura.
            </Typography>

            <Box component="form" onSubmit={updatePassword}>
                <Stack spacing={2.5} sx={{ maxWidth: 480 }}>
                    <TextField
                        label="Senha atual"
                        type="password"
                        inputRef={currentPasswordInput}
                        value={data.current_password}
                        autoComplete="current-password"
                        fullWidth
                        error={Boolean(errors.current_password)}
                        helperText={errors.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                    />

                    <TextField
                        label="Nova senha"
                        type="password"
                        inputRef={passwordInput}
                        value={data.password}
                        autoComplete="new-password"
                        fullWidth
                        error={Boolean(errors.password)}
                        helperText={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <TextField
                        label="Confirmar nova senha"
                        type="password"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        fullWidth
                        error={Boolean(errors.password_confirmation)}
                        helperText={errors.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />

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
