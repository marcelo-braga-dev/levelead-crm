import { companyImportFieldLabels } from '@/utils/companyImportFields';
import { useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    CardContent,
    Checkbox,
    FormControlLabel,
    MenuItem,
    Select,
    Stack,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { useState } from 'react';

interface ImportProfile {
    id: number;
    name: string;
    // Eloquent serializa a relação `columnMappings()` como `column_mappings` (Str::snake do método).
    column_mappings: { source_column_label: string; target_field: string }[];
}

export default function ImportMappingStep({
    batchId,
    header,
    suggestedMapping,
    targetFields,
    profiles,
    totalRows,
}: {
    batchId: number;
    header: string[];
    suggestedMapping: Record<string, string | null>;
    targetFields: string[];
    profiles: ImportProfile[];
    totalRows: number;
}) {
    const [mapping, setMapping] = useState<Record<string, string | null>>(suggestedMapping);
    const [saveAsProfile, setSaveAsProfile] = useState(false);
    const [profileName, setProfileName] = useState('');
    const form = useForm({});

    function applyProfile(profile: ImportProfile) {
        const next = { ...mapping };

        for (const columnMapping of profile.column_mappings) {
            if (header.includes(columnMapping.source_column_label)) {
                next[columnMapping.source_column_label] = columnMapping.target_field;
            }
        }

        setMapping(next);
    }

    function submit() {
        form.transform(() => ({
            mapping,
            save_as_profile: saveAsProfile,
            profile_name: profileName,
        }));
        form.post(route('companies.import.confirmMapping', batchId));
    }

    return (
        <Card>
            <CardContent>
                <Typography variant="h6" gutterBottom>
                    2. Mapear colunas
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                    {totalRows} linha(s) detectada(s). Ajuste o campo de destino das colunas que
                    não foram identificadas automaticamente.
                </Typography>

                {profiles.length > 0 && (
                    <Stack direction="row" spacing={2} sx={{ mb: 2, alignItems: 'center' }}>
                        <Typography variant="body2">Aplicar perfil salvo:</Typography>
                        {profiles.map((profile) => (
                            <Button key={profile.id} size="small" onClick={() => applyProfile(profile)}>
                                {profile.name}
                            </Button>
                        ))}
                    </Stack>
                )}

                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Coluna do arquivo</TableCell>
                            <TableCell>Campo do sistema</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {header.map((column) => (
                            <TableRow key={column}>
                                <TableCell>{column}</TableCell>
                                <TableCell>
                                    <Select
                                        size="small"
                                        fullWidth
                                        value={mapping[column] ?? ''}
                                        onChange={(e) =>
                                            setMapping({ ...mapping, [column]: e.target.value || null })
                                        }
                                    >
                                        <MenuItem value="">— Não mapear —</MenuItem>
                                        {targetFields.map((field) => (
                                            <MenuItem key={field} value={field}>
                                                {companyImportFieldLabels[field] ?? field}
                                            </MenuItem>
                                        ))}
                                    </Select>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                <Stack spacing={1} sx={{ mt: 3, maxWidth: 480 }}>
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={saveAsProfile}
                                onChange={(e) => setSaveAsProfile(e.target.checked)}
                            />
                        }
                        label="Salvar este mapeamento como perfil reutilizável"
                    />
                    {saveAsProfile && (
                        <TextField
                            label="Nome do perfil"
                            size="small"
                            value={profileName}
                            onChange={(e) => setProfileName(e.target.value)}
                        />
                    )}

                    <Button variant="contained" onClick={submit} disabled={form.processing}>
                        Continuar
                    </Button>
                </Stack>
            </CardContent>
        </Card>
    );
}
