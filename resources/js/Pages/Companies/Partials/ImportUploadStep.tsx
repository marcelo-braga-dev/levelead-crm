import { useForm } from '@inertiajs/react';
import { Button, Card, CardContent, Stack, TextField, Typography } from '@mui/material';
import { ChangeEvent } from 'react';

export default function ImportUploadStep() {
    const form = useForm<{ file: File | null; data_provider: string; license_reference: string }>({
        file: null,
        data_provider: '',
        license_reference: '',
    });

    function handleFile(event: ChangeEvent<HTMLInputElement>) {
        form.setData('file', event.target.files?.[0] ?? null);
    }

    function submit() {
        form.post(route('companies.import.store'), { forceFormData: true });
    }

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    1. Enviar arquivo CSV
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                    Sócios (company_partners) não fazem parte deste wizard — só campos cadastrais
                    do lead e um contato principal por tipo.
                </Typography>

                <Stack spacing={2} sx={{ maxWidth: 480 }}>
                    <Button variant="outlined" component="label">
                        {form.data.file ? form.data.file.name : 'Selecionar arquivo CSV'}
                        <input type="file" accept=".csv,.txt" hidden onChange={handleFile} />
                    </Button>
                    {form.errors.file && (
                        <Typography color="error" variant="body2">
                            {form.errors.file}
                        </Typography>
                    )}

                    <TextField
                        label="Fonte dos dados (opcional)"
                        size="small"
                        value={form.data.data_provider}
                        onChange={(e) => form.setData('data_provider', e.target.value)}
                    />
                    <TextField
                        label="Referência de licença (opcional)"
                        size="small"
                        value={form.data.license_reference}
                        onChange={(e) => form.setData('license_reference', e.target.value)}
                    />

                    <Button
                        variant="contained"
                        onClick={submit}
                        disabled={!form.data.file || form.processing}
                    >
                        Continuar
                    </Button>
                </Stack>
            </CardContent>
        </Card>
    );
}
