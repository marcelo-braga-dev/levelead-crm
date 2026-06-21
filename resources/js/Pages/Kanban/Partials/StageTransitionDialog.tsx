import { topAlignedDialogSlotProps } from '@/utils/dialog';
import { leadStageLabels, LeadStageValue } from '@/utils/leadStage';
import { useForm } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    MenuItem,
    Select,
    Stack,
    TextField,
    Typography,
} from '@mui/material';

export type StageTransitionKind = 'lost' | 'won' | 'regression';

const lossReasonOptions = [
    { value: 'no_budget', label: 'Sem orçamento' },
    { value: 'bad_timing', label: 'Timing ruim' },
    { value: 'competition', label: 'Concorrência' },
    { value: 'no_interest', label: 'Sem interesse' },
    { value: 'invalid_data', label: 'Dados inválidos' },
    { value: 'other', label: 'Outro' },
];

export default function StageTransitionDialog({
    leadId,
    toStage,
    kind,
    products,
    onClose,
}: {
    leadId: number;
    toStage: LeadStageValue;
    kind: StageTransitionKind;
    products: { id: number; name: string }[];
    onClose: () => void;
}) {
    const form = useForm({
        to_stage: toStage,
        reason: '',
        loss_reason: '',
        loss_notes: '',
        won_value: '',
        won_product_id: '',
    });

    function submit() {
        form.patch(route('leads.stage.update', leadId), { onSuccess: onClose });
    }

    return (
        <Dialog open onClose={onClose} maxWidth="sm" fullWidth slotProps={topAlignedDialogSlotProps}>
            <DialogTitle>Mover para {leadStageLabels[toStage]}</DialogTitle>
            <DialogContent>
                <Stack spacing={2} sx={{ mt: 1 }}>
                    {kind === 'lost' && (
                        <>
                            <Select
                                size="small"
                                displayEmpty
                                value={form.data.loss_reason}
                                onChange={(e) => form.setData('loss_reason', e.target.value)}
                            >
                                <MenuItem value="">Selecione o motivo da perda</MenuItem>
                                {lossReasonOptions.map((option) => (
                                    <MenuItem key={option.value} value={option.value}>
                                        {option.label}
                                    </MenuItem>
                                ))}
                            </Select>
                            {form.errors.loss_reason && (
                                <Typography color="error" variant="body2">
                                    {form.errors.loss_reason}
                                </Typography>
                            )}
                            <TextField
                                label="Notas (opcional)"
                                size="small"
                                multiline
                                minRows={2}
                                value={form.data.loss_notes}
                                onChange={(e) => form.setData('loss_notes', e.target.value)}
                            />
                        </>
                    )}

                    {kind === 'won' && (
                        <>
                            <TextField
                                label="Valor do fechamento"
                                size="small"
                                type="number"
                                value={form.data.won_value}
                                onChange={(e) => form.setData('won_value', e.target.value)}
                            />
                            {form.errors.won_value && (
                                <Typography color="error" variant="body2">
                                    {form.errors.won_value}
                                </Typography>
                            )}
                            <Select
                                size="small"
                                displayEmpty
                                value={form.data.won_product_id}
                                onChange={(e) => form.setData('won_product_id', e.target.value)}
                            >
                                <MenuItem value="">Produto (opcional)</MenuItem>
                                {products.map((product) => (
                                    <MenuItem key={product.id} value={product.id}>
                                        {product.name}
                                    </MenuItem>
                                ))}
                            </Select>
                        </>
                    )}

                    {kind === 'regression' && (
                        <>
                            <TextField
                                label="Motivo do retrocesso"
                                size="small"
                                multiline
                                minRows={2}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                            />
                            {form.errors.to_stage && (
                                <Typography color="error" variant="body2">
                                    {form.errors.to_stage}
                                </Typography>
                            )}
                        </>
                    )}
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancelar</Button>
                <Button variant="contained" onClick={submit} disabled={form.processing}>
                    Confirmar
                </Button>
            </DialogActions>
        </Dialog>
    );
}
