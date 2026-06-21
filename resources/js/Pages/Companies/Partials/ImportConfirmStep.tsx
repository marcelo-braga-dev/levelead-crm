import { companyImportFieldLabels } from '@/utils/companyImportFields';
import { router } from '@inertiajs/react';
import {
    Alert,
    Button,
    Card,
    CardContent,
    Chip,
    List,
    ListItem,
    ListItemText,
    Stack,
    Typography,
} from '@mui/material';

export interface ImportBatchError {
    row_number: number;
    error_message: string;
}

export interface ImportBatch {
    id: number;
    file_name: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    created_rows: number;
    updated_rows: number;
    skipped_rows: number;
    errors?: ImportBatchError[];
}

export default function ImportConfirmStep({
    batch,
    mapping,
    totalRows,
}: {
    batch: ImportBatch;
    mapping: Record<string, string | null> | null;
    totalRows: number;
}) {
    const mappedFields = Object.values(mapping ?? {}).filter((value): value is string => value !== null);
    const isProcessed = batch.status === 'completed' || batch.status === 'failed';

    function process() {
        router.post(route('companies.import.process', batch.id));
    }

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    3. Confirmar e processar
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                    Arquivo: {batch.file_name} — {totalRows} linha(s) detectada(s).
                </Typography>

                <Stack direction="row" spacing={1} sx={{ mb: 2, flexWrap: 'wrap' }}>
                    {mappedFields.map((field) => (
                        <Chip key={field} label={companyImportFieldLabels[field] ?? field} size="small" />
                    ))}
                </Stack>

                {!isProcessed && (
                    <Button variant="contained" onClick={process}>
                        Processar importação
                    </Button>
                )}

                {isProcessed && (
                    <Stack spacing={2}>
                        <Alert severity={batch.status === 'completed' ? 'success' : 'error'}>
                            Importação {batch.status === 'completed' ? 'concluída' : 'falhou'}: {batch.created_rows}{' '}
                            criada(s), {batch.updated_rows} atualizada(s), {batch.skipped_rows} ignorada(s).
                        </Alert>

                        {batch.errors && batch.errors.length > 0 && (
                            <>
                                <Typography variant="subtitle2">Linhas ignoradas</Typography>
                                <List dense>
                                    {batch.errors.map((error) => (
                                        <ListItem key={error.row_number} disableGutters>
                                            <ListItemText
                                                primary={`Linha ${error.row_number}`}
                                                secondary={error.error_message}
                                            />
                                        </ListItem>
                                    ))}
                                </List>
                            </>
                        )}
                    </Stack>
                )}
            </CardContent>
        </Card>
    );
}
