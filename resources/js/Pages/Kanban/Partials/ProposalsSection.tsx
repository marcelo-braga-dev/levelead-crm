import { ProposalEntry } from '@/types';
import { formatCurrency, formatDate } from '@/utils/format';
import { useForm } from '@inertiajs/react';
import {
    Button,
    Chip,
    Link,
    List,
    ListItem,
    ListItemText,
    MenuItem,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { ChangeEvent } from 'react';

export default function ProposalsSection({
    leadId,
    proposals,
    products,
}: {
    leadId: number;
    proposals: ProposalEntry[];
    products: { id: number; name: string }[];
}) {
    const form = useForm<{ value: string; notes: string; product_id: string; attachments: File[] }>({
        value: '',
        notes: '',
        product_id: '',
        attachments: [],
    });

    function submit() {
        form.post(route('proposals.store', leadId), {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }

    function onFilesSelected(e: ChangeEvent<HTMLInputElement>) {
        form.setData('attachments', e.target.files ? Array.from(e.target.files) : []);
    }

    return (
        <Stack spacing={2}>
            <Stack spacing={1.5}>
                <TextField
                    label="Valor da proposta"
                    size="small"
                    type="number"
                    value={form.data.value}
                    onChange={(e) => form.setData('value', e.target.value)}
                />
                <TextField
                    select
                    label="Produto (opcional)"
                    size="small"
                    value={form.data.product_id}
                    onChange={(e) => form.setData('product_id', e.target.value)}
                >
                    <MenuItem value="">Sem produto definido</MenuItem>
                    {products.map((product) => (
                        <MenuItem key={product.id} value={product.id}>
                            {product.name}
                        </MenuItem>
                    ))}
                </TextField>
                <TextField
                    label="Notas"
                    size="small"
                    multiline
                    minRows={2}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
                <Button component="label" variant="outlined" size="small">
                    {form.data.attachments.length > 0
                        ? `${form.data.attachments.length} arquivo(s) selecionado(s)`
                        : 'Anexar arquivos (opcional)'}
                    <input type="file" multiple hidden onChange={onFilesSelected} />
                </Button>
                {form.errors.value && (
                    <Typography color="error" variant="body2">
                        {form.errors.value}
                    </Typography>
                )}
                <Button variant="contained" size="small" onClick={submit} disabled={form.processing}>
                    Registrar nova versão
                </Button>
            </Stack>

            <List dense disablePadding>
                {proposals.length === 0 && (
                    <Typography variant="body2" color="text.secondary">
                        Nenhuma proposta registrada ainda.
                    </Typography>
                )}
                {proposals.map((proposal) => (
                    <ListItem key={proposal.id} disableGutters alignItems="flex-start" sx={{ display: 'block', mb: 1 }}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                            <Typography variant="body2" sx={{ fontWeight: 'bold' }}>
                                Versão {proposal.version}
                            </Typography>
                            <Chip
                                size="small"
                                label={proposal.status === 'active' ? 'Ativa' : 'Substituída'}
                                color={proposal.status === 'active' ? 'success' : 'default'}
                            />
                            {proposal.product && <Chip size="small" label={proposal.product.name} variant="outlined" />}
                            {formatCurrency(proposal.value) && (
                                <Typography variant="body2">{formatCurrency(proposal.value)}</Typography>
                            )}
                        </Stack>
                        <ListItemText
                            secondary={[
                                formatDate(proposal.created_at),
                                proposal.created_by?.name,
                                proposal.notes,
                            ]
                                .filter(Boolean)
                                .join(' — ')}
                        />
                        {proposal.attachments.length > 0 && (
                            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                                {proposal.attachments.map((attachment) => (
                                    <Link
                                        key={attachment.id}
                                        href={route('proposals.attachments.download', attachment.id)}
                                        underline="hover"
                                        variant="body2"
                                    >
                                        {attachment.original_name}
                                    </Link>
                                ))}
                            </Stack>
                        )}
                    </ListItem>
                ))}
            </List>
        </Stack>
    );
}
