import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    Paper,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    TextField,
} from '@mui/material';
import { useState } from 'react';

interface TeamRow {
    id: number;
    name: string;
    users_count: number;
}

export default function TeamsIndex({ teams }: { teams: TeamRow[] }) {
    const [editing, setEditing] = useState<TeamRow | 'new' | null>(null);
    const form = useForm<{ name: string }>({ name: '' });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditing('new');
    }

    function openEdit(team: TeamRow) {
        form.setData({ name: team.name });
        form.clearErrors();
        setEditing(team);
    }

    function submit() {
        const onSuccess = () => setEditing(null);

        if (editing === 'new') {
            form.post(route('admin.teams.store'), { onSuccess });
        } else if (editing) {
            form.patch(route('admin.teams.update', editing.id), { onSuccess });
        }
    }

    function destroy(team: TeamRow) {
        if (confirm(`Remover a equipe "${team.name}"? Usuários e leads dessa equipe ficam sem equipe.`)) {
            router.delete(route('admin.teams.destroy', team.id));
        }
    }

    return (
        <MuiAuthenticatedLayout title="Equipes">
            <Head title="Equipes" />

            <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 2 }}>
                <Button variant="contained" onClick={openCreate}>
                    Nova equipe
                </Button>
            </Stack>

            <TableContainer component={Paper}>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Nome</TableCell>
                            <TableCell>Membros</TableCell>
                            <TableCell align="right">Ações</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {teams.map((team) => (
                            <TableRow key={team.id} hover>
                                <TableCell>{team.name}</TableCell>
                                <TableCell>{team.users_count}</TableCell>
                                <TableCell align="right">
                                    <Button size="small" onClick={() => openEdit(team)}>
                                        Editar
                                    </Button>
                                    <Button size="small" color="error" onClick={() => destroy(team)}>
                                        Remover
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                        {teams.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={3} align="center">
                                    Nenhuma equipe cadastrada.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            <Dialog open={editing !== null} onClose={() => setEditing(null)} maxWidth="sm" fullWidth>
                <DialogTitle>{editing === 'new' ? 'Nova equipe' : 'Editar equipe'}</DialogTitle>
                <DialogContent>
                    <TextField
                        label="Nome"
                        size="small"
                        fullWidth
                        sx={{ mt: 1 }}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={Boolean(form.errors.name)}
                        helperText={form.errors.name}
                    />
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setEditing(null)}>Cancelar</Button>
                    <Button variant="contained" onClick={submit} disabled={form.processing}>
                        Salvar
                    </Button>
                </DialogActions>
            </Dialog>
        </MuiAuthenticatedLayout>
    );
}
