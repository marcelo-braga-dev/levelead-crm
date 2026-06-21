import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { PageProps, UserRole } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
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

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    team: { id: number; name: string } | null;
}

const roleLabels: Record<UserRole, string> = {
    admin: 'Admin',
    manager: 'Gestor',
    consultant: 'Consultor',
};

export default function UsersIndex({ users, teams }: { users: UserRow[]; teams: { id: number; name: string }[] }) {
    const { auth } = usePage<PageProps>().props;
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);

    const form = useForm<{ name: string; email: string; password: string; role: UserRole; team_id: string }>({
        name: '',
        email: '',
        password: '',
        role: 'consultant',
        team_id: '',
    });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditing('new');
    }

    function openEdit(user: UserRow) {
        form.setData({
            name: user.name,
            email: user.email,
            password: '',
            role: user.role,
            team_id: user.team ? String(user.team.id) : '',
        });
        form.clearErrors();
        setEditing(user);
    }

    function submit() {
        const onSuccess = () => setEditing(null);

        if (editing === 'new') {
            form.post(route('admin.users.store'), { onSuccess });
        } else if (editing) {
            form.patch(route('admin.users.update', editing.id), { onSuccess });
        }
    }

    function deactivate(user: UserRow) {
        if (confirm(`Desativar ${user.name}? O usuário não conseguirá mais entrar no sistema.`)) {
            router.delete(route('admin.users.destroy', user.id));
        }
    }

    return (
        <MuiAuthenticatedLayout title="Usuários">
            <Head title="Usuários" />

            <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 2 }}>
                <Button variant="contained" onClick={openCreate}>
                    Novo usuário
                </Button>
            </Stack>

            <TableContainer component={Paper}>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Nome</TableCell>
                            <TableCell>E-mail</TableCell>
                            <TableCell>Papel</TableCell>
                            <TableCell>Equipe</TableCell>
                            <TableCell align="right">Ações</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {users.map((user) => (
                            <TableRow key={user.id} hover>
                                <TableCell>{user.name}</TableCell>
                                <TableCell>{user.email}</TableCell>
                                <TableCell>{roleLabels[user.role]}</TableCell>
                                <TableCell>{user.team?.name ?? '—'}</TableCell>
                                <TableCell align="right">
                                    <Button size="small" onClick={() => openEdit(user)}>
                                        Editar
                                    </Button>
                                    {user.id !== auth.user.id && (
                                        <Button size="small" color="error" onClick={() => deactivate(user)}>
                                            Desativar
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                        {users.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5} align="center">
                                    Nenhum usuário cadastrado.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            <Dialog open={editing !== null} onClose={() => setEditing(null)} maxWidth="sm" fullWidth>
                <DialogTitle>{editing === 'new' ? 'Novo usuário' : 'Editar usuário'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField
                            label="Nome"
                            size="small"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={Boolean(form.errors.name)}
                            helperText={form.errors.name}
                        />
                        <TextField
                            label="E-mail"
                            size="small"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            error={Boolean(form.errors.email)}
                            helperText={form.errors.email}
                        />
                        <TextField
                            label={editing === 'new' ? 'Senha' : 'Nova senha (deixe em branco para manter)'}
                            type="password"
                            size="small"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            error={Boolean(form.errors.password)}
                            helperText={form.errors.password}
                        />
                        <TextField
                            select
                            label="Papel"
                            size="small"
                            value={form.data.role}
                            onChange={(e) => form.setData('role', e.target.value as UserRole)}
                        >
                            <MenuItem value="admin">Admin</MenuItem>
                            <MenuItem value="manager">Gestor</MenuItem>
                            <MenuItem value="consultant">Consultor</MenuItem>
                        </TextField>
                        <TextField
                            select
                            label="Equipe"
                            size="small"
                            value={form.data.team_id}
                            onChange={(e) => form.setData('team_id', e.target.value)}
                        >
                            <MenuItem value="">Sem equipe</MenuItem>
                            {teams.map((team) => (
                                <MenuItem key={team.id} value={team.id}>
                                    {team.name}
                                </MenuItem>
                            ))}
                        </TextField>
                    </Stack>
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
