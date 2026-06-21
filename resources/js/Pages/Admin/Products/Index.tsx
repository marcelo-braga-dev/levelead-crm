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

interface ProductRow {
    id: number;
    name: string;
}

export default function ProductsIndex({ products }: { products: ProductRow[] }) {
    const [editing, setEditing] = useState<ProductRow | 'new' | null>(null);
    const form = useForm<{ name: string }>({ name: '' });

    function openCreate() {
        form.reset();
        form.clearErrors();
        setEditing('new');
    }

    function openEdit(product: ProductRow) {
        form.setData({ name: product.name });
        form.clearErrors();
        setEditing(product);
    }

    function submit() {
        const onSuccess = () => setEditing(null);

        if (editing === 'new') {
            form.post(route('admin.products.store'), { onSuccess });
        } else if (editing) {
            form.patch(route('admin.products.update', editing.id), { onSuccess });
        }
    }

    function destroy(product: ProductRow) {
        if (confirm(`Remover o produto "${product.name}"? Propostas/leads que já o referenciam continuam intactos.`)) {
            router.delete(route('admin.products.destroy', product.id));
        }
    }

    return (
        <MuiAuthenticatedLayout title="Produtos">
            <Head title="Produtos" />

            <Stack direction="row" sx={{ justifyContent: 'flex-end', mb: 2 }}>
                <Button variant="contained" onClick={openCreate}>
                    Novo produto
                </Button>
            </Stack>

            <TableContainer component={Paper}>
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Nome</TableCell>
                            <TableCell align="right">Ações</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {products.map((product) => (
                            <TableRow key={product.id} hover>
                                <TableCell>{product.name}</TableCell>
                                <TableCell align="right">
                                    <Button size="small" onClick={() => openEdit(product)}>
                                        Editar
                                    </Button>
                                    <Button size="small" color="error" onClick={() => destroy(product)}>
                                        Remover
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                        {products.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={2} align="center">
                                    Nenhum produto cadastrado.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </TableContainer>

            <Dialog open={editing !== null} onClose={() => setEditing(null)} maxWidth="sm" fullWidth>
                <DialogTitle>{editing === 'new' ? 'Novo produto' : 'Editar produto'}</DialogTitle>
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
