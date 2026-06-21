import { useForm } from '@inertiajs/react';
import { Box, Button, Dialog, DialogActions, DialogContent, DialogTitle, Stack, TextField, Typography } from '@mui/material';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <Box>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
                Excluir conta
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                Após a exclusão da conta, todos os seus recursos e dados serão permanentemente
                excluídos. Antes de excluir sua conta, faça o download de qualquer dado que queira
                manter.
            </Typography>

            <Button variant="contained" color="error" onClick={confirmUserDeletion}>
                Excluir conta
            </Button>

            <Dialog open={confirmingUserDeletion} onClose={closeModal} component="form" onSubmit={deleteUser}>
                <DialogTitle>Tem certeza que deseja excluir sua conta?</DialogTitle>
                <DialogContent>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                        Após a exclusão, todos os recursos e dados da conta serão permanentemente
                        excluídos. Informe sua senha para confirmar que deseja excluir permanentemente
                        sua conta.
                    </Typography>

                    <TextField
                        label="Senha"
                        type="password"
                        inputRef={passwordInput}
                        value={data.password}
                        autoFocus
                        fullWidth
                        error={Boolean(errors.password)}
                        helperText={errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </DialogContent>
                <DialogActions>
                    <Stack direction="row" spacing={1.5} sx={{ p: 1 }}>
                        <Button onClick={closeModal}>Cancelar</Button>
                        <Button type="submit" variant="contained" color="error" disabled={processing}>
                            Excluir conta
                        </Button>
                    </Stack>
                </DialogActions>
            </Dialog>
        </Box>
    );
}
