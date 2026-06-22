import { CompanyAddress, CompanyPlacesProfile } from '@/types';
import { formatCep, unmask } from '@/utils/format';
import { router, useForm } from '@inertiajs/react';
import RoomIcon from '@mui/icons-material/Room';
import {
    Alert,
    Button,
    Divider,
    Grid,
    MenuItem,
    Paper,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { useEffect, useState } from 'react';
import { MapContainer, Marker, Popup, TileLayer } from 'react-leaflet';

// Os ícones default do Leaflet referenciam caminhos relativos que não resolvem sob o
// bundling do Vite — sem isso, o marcador aparece quebrado (ícone ausente).
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

interface CityOption {
    id: number;
    name: string;
}

export default function MapSection({
    companyId,
    razaoSocial,
    address,
    placesProfile,
    states,
    canEditAddress,
}: {
    companyId: number;
    razaoSocial: string;
    address: CompanyAddress | null;
    placesProfile: CompanyPlacesProfile | null;
    states: { id: number; uf: string }[];
    canEditAddress: boolean;
}) {
    const form = useForm({
        logradouro: address?.logradouro ?? '',
        numero: address?.numero ?? '',
        complemento: address?.complemento ?? '',
        bairro: address?.bairro ?? '',
        cep: address?.cep ?? '',
        state_id: address?.state?.id ? String(address.state.id) : '',
        city_id: address?.city?.id ? String(address.city.id) : '',
    });

    const [cities, setCities] = useState<CityOption[]>(address?.city ? [address.city] : []);
    const [loadingCities, setLoadingCities] = useState(false);
    const [cepLookupLoading, setCepLookupLoading] = useState(false);
    const [cepLookupFailed, setCepLookupFailed] = useState(false);

    useEffect(() => {
        if (!form.data.state_id) {
            setCities([]);

            return;
        }

        setLoadingCities(true);
        window.axios
            .get<CityOption[]>(route('lookups.cities'), { params: { state_id: form.data.state_id } })
            .then((response) => setCities(response.data))
            .finally(() => setLoadingCities(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.state_id]);

    interface CepLookupResponse {
        found: boolean;
        logradouro: string | null;
        bairro: string | null;
        city_id: number | null;
        state_id: number | null;
        city_name: string | null;
    }

    function handleCepChange(value: string) {
        const digits = unmask(value).slice(0, 8);
        form.setData('cep', digits);
        setCepLookupFailed(false);

        if (digits.length !== 8) {
            return;
        }

        setCepLookupLoading(true);
        window.axios
            .get<CepLookupResponse>(route('lookups.cep'), { params: { cep: digits } })
            .then((response) => {
                const data = response.data;

                if (!data.found || !data.city_id || !data.state_id) {
                    setCepLookupFailed(true);

                    return;
                }

                if (data.city_name) {
                    setCities((current) =>
                        current.some((city) => city.id === data.city_id)
                            ? current
                            : [...current, { id: data.city_id as number, name: data.city_name as string }],
                    );
                }

                form.setData((current) => ({
                    ...current,
                    logradouro: data.logradouro ?? current.logradouro,
                    bairro: data.bairro ?? current.bairro,
                    state_id: String(data.state_id),
                    city_id: String(data.city_id),
                }));
            })
            .catch(() => setCepLookupFailed(true))
            .finally(() => setCepLookupLoading(false));
    }

    function saveAddress() {
        form.patch(route('companies.address.update', companyId), { preserveScroll: true });
    }

    function syncLocation() {
        router.post(route('companies.places-profile.store', companyId));
    }

    const isAddressComplete = Boolean(form.data.logradouro && form.data.state_id && form.data.city_id);

    const latitude = placesProfile?.latitude !== null && placesProfile?.latitude !== undefined
        ? Number(placesProfile.latitude)
        : null;
    const longitude = placesProfile?.longitude !== null && placesProfile?.longitude !== undefined
        ? Number(placesProfile.longitude)
        : null;
    const hasLocation = latitude !== null && longitude !== null;

    return (
        <Stack spacing={2}>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Typography variant="subtitle2" gutterBottom>
                    Endereço
                </Typography>

                {canEditAddress ? (
                    <Stack spacing={2}>
                        <Grid container spacing={2}>
                            <Grid size={{ xs: 12, sm: 4 }}>
                                <TextField
                                    label="CEP"
                                    size="small"
                                    fullWidth
                                    value={formatCep(form.data.cep)}
                                    onChange={(e) => handleCepChange(e.target.value)}
                                    helperText={
                                        cepLookupLoading
                                            ? 'Buscando endereço...'
                                            : cepLookupFailed
                                                ? 'CEP não encontrado — preencha o endereço manualmente.'
                                                : ' '
                                    }
                                />
                            </Grid>
                            <Grid size={{ xs: 12, sm: 8 }}>
                                <TextField
                                    label="Logradouro"
                                    size="small"
                                    fullWidth
                                    value={form.data.logradouro}
                                    onChange={(e) => form.setData('logradouro', e.target.value)}
                                />
                            </Grid>
                            <Grid size={{ xs: 12, sm: 4 }}>
                                <TextField
                                    label="Número"
                                    size="small"
                                    fullWidth
                                    value={form.data.numero}
                                    onChange={(e) => form.setData('numero', e.target.value)}
                                />
                            </Grid>
                            <Grid size={{ xs: 12, sm: 4 }}>
                                <TextField
                                    label="Complemento"
                                    size="small"
                                    fullWidth
                                    value={form.data.complemento}
                                    onChange={(e) => form.setData('complemento', e.target.value)}
                                />
                            </Grid>
                            <Grid size={{ xs: 12, sm: 4 }}>
                                <TextField
                                    label="Bairro"
                                    size="small"
                                    fullWidth
                                    value={form.data.bairro}
                                    onChange={(e) => form.setData('bairro', e.target.value)}
                                />
                            </Grid>
                            <Grid size={{ xs: 12, sm: 6 }}>
                                <TextField
                                    select
                                    label="Estado"
                                    size="small"
                                    fullWidth
                                    value={form.data.state_id}
                                    onChange={(e) => {
                                        form.setData('state_id', e.target.value);
                                        form.setData('city_id', '');
                                    }}
                                >
                                    <MenuItem value="">—</MenuItem>
                                    {states.map((state) => (
                                        <MenuItem key={state.id} value={String(state.id)}>
                                            {state.uf}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </Grid>
                            <Grid size={{ xs: 12, sm: 6 }}>
                                <TextField
                                    select
                                    label="Cidade"
                                    size="small"
                                    fullWidth
                                    disabled={!form.data.state_id || loadingCities}
                                    value={form.data.city_id}
                                    onChange={(e) => form.setData('city_id', e.target.value)}
                                >
                                    <MenuItem value="">—</MenuItem>
                                    {cities.map((city) => (
                                        <MenuItem key={city.id} value={String(city.id)}>
                                            {city.name}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </Grid>
                        </Grid>
                        <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
                            <Button size="small" variant="contained" disabled={form.processing} onClick={saveAddress}>
                                Salvar endereço
                            </Button>
                        </Stack>
                    </Stack>
                ) : (
                    <Typography variant="body2" color="text.secondary">
                        {address?.logradouro
                            ? `${address.logradouro}${address.numero ? ', ' + address.numero : ''}${address.bairro ? ' - ' + address.bairro : ''}${address.city ? ' - ' + address.city.name + '/' + (address.state?.uf ?? '') : ''}`
                            : 'Endereço não cadastrado.'}
                    </Typography>
                )}
            </Paper>

            <Divider />

            {!isAddressComplete && (
                <Alert severity="info">
                    Preencha logradouro, estado e cidade para uma busca de localização mais precisa.
                </Alert>
            )}

            {!hasLocation ? (
                <Paper variant="outlined" sx={{ p: 3, textAlign: 'center' }}>
                    <RoomIcon sx={{ fontSize: 40, color: 'text.disabled', mb: 1 }} />
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                        Ainda não há localização sincronizada para este lead. Busque a localização via
                        Google para obter as coordenadas da empresa.
                    </Typography>
                    <Button variant="outlined" size="small" onClick={syncLocation}>
                        Buscar localização via Google
                    </Button>
                </Paper>
            ) : (
                <Stack spacing={1}>
                    <Paper variant="outlined" sx={{ overflow: 'hidden', borderRadius: 2 }}>
                        <MapContainer
                            center={[latitude as number, longitude as number]}
                            zoom={16}
                            scrollWheelZoom={false}
                            style={{ height: 420, width: '100%' }}
                        >
                            <TileLayer
                                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                            />
                            <Marker position={[latitude as number, longitude as number]}>
                                <Popup>{razaoSocial}</Popup>
                            </Marker>
                        </MapContainer>
                    </Paper>
                    <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
                        <Typography variant="caption" color="text.secondary">
                            {(latitude as number).toFixed(6)}, {(longitude as number).toFixed(6)}
                        </Typography>
                        <Button size="small" onClick={syncLocation}>
                            Atualizar localização
                        </Button>
                    </Stack>
                </Stack>
            )}
        </Stack>
    );
}
