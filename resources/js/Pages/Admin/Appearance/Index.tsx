import { useColorMode } from '@/Contexts/ThemeContext';
import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { createAppTheme } from '@/theme';
import { Head, useForm } from '@inertiajs/react';
import {
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    Grid,
    Paper,
    Stack,
    ThemeProvider,
    Typography,
} from '@mui/material';
import ColorPickerField from './Partials/ColorPickerField';

interface AppearanceColors {
    primary_color: string;
    secondary_color: string;
}

export default function AppearanceIndex({
    current,
    defaults,
}: {
    current: AppearanceColors;
    defaults: AppearanceColors;
}) {
    const { mode } = useColorMode();
    const form = useForm<AppearanceColors>(current);

    const previewTheme = createAppTheme(mode, form.data.primary_color, form.data.secondary_color);

    function submit() {
        form.put(route('admin.appearance.update'));
    }

    function restoreDefaults() {
        form.setData(defaults);
    }

    return (
        <MuiAuthenticatedLayout title="Aparência">
            <Head title="Aparência" />

            <Grid container spacing={3}>
                <Grid size={{ xs: 12, md: 5 }}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="h6" sx={{ mb: 2 }}>
                            Paleta de cores
                        </Typography>

                        <Stack spacing={3}>
                            <ColorPickerField
                                label="Primary"
                                value={form.data.primary_color}
                                onChange={(value) => form.setData('primary_color', value)}
                                error={form.errors.primary_color}
                            />
                            <ColorPickerField
                                label="Secondary"
                                value={form.data.secondary_color}
                                onChange={(value) => form.setData('secondary_color', value)}
                                error={form.errors.secondary_color}
                            />
                        </Stack>

                        <Stack direction="row" spacing={2} sx={{ mt: 4 }}>
                            <Button variant="contained" onClick={submit} disabled={form.processing}>
                                Salvar
                            </Button>
                            <Button variant="text" onClick={restoreDefaults} disabled={form.processing}>
                                Restaurar padrão
                            </Button>
                        </Stack>
                    </Paper>
                </Grid>

                <Grid size={{ xs: 12, md: 7 }}>
                    <Paper sx={{ p: 3 }}>
                        <Typography variant="h6" sx={{ mb: 2 }}>
                            Pré-visualização
                        </Typography>

                        <ThemeProvider theme={previewTheme}>
                            <Box
                                sx={{
                                    p: 3,
                                    borderRadius: 2,
                                    bgcolor: 'background.default',
                                    border: '1px solid',
                                    borderColor: 'divider',
                                }}
                            >
                                <Stack spacing={2}>
                                    <Stack direction="row" spacing={1.5}>
                                        <Button variant="contained" color="primary">
                                            Botão Primary
                                        </Button>
                                        <Button variant="contained" color="secondary">
                                            Botão Secondary
                                        </Button>
                                        <Button variant="outlined" color="primary">
                                            Outline
                                        </Button>
                                    </Stack>

                                    <Card>
                                        <CardContent>
                                            <Stack direction="row" spacing={1} sx={{ mb: 1, alignItems: 'center' }}>
                                                <Chip label="Hot" color="primary" size="small" />
                                                <Chip label="Em negociação" color="secondary" size="small" />
                                            </Stack>
                                            <Typography variant="subtitle1">Card de exemplo</Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                É assim que cards, badges e botões vão aparecer no resto da
                                                aplicação com essa paleta.
                                            </Typography>
                                        </CardContent>
                                    </Card>
                                </Stack>
                            </Box>
                        </ThemeProvider>
                    </Paper>
                </Grid>
            </Grid>
        </MuiAuthenticatedLayout>
    );
}
