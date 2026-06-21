import { GooglePlacesBadgeThresholds } from '@/Pages/Kanban/Board';
import { CompanyPlacesProfile } from '@/types';
import { router } from '@inertiajs/react';
import { Button, Chip, Paper, Stack, Typography } from '@mui/material';

const businessStatusLabels: Record<string, string> = {
    OPERATIONAL: 'Em operação',
    CLOSED_TEMPORARILY: 'Fechado temporariamente',
    CLOSED_PERMANENTLY: 'Fechado permanentemente',
};

export default function GooglePlacesCard({
    companyId,
    razaoSocial,
    hasSite,
    placesProfile,
    thresholds,
}: {
    companyId: number;
    razaoSocial: string;
    hasSite: boolean;
    placesProfile: CompanyPlacesProfile | null;
    thresholds: GooglePlacesBadgeThresholds;
}) {
    function sync() {
        router.post(route('companies.places-profile.store', companyId));
    }

    const badges: string[] = [];

    if (!hasSite) {
        badges.push('Sem site');
    }

    if (placesProfile === null) {
        badges.push('Perfil incompleto');
    } else {
        if ((placesProfile.user_rating_count ?? 0) < thresholds.low_review_count) {
            badges.push('Poucas avaliações');
        }
        if (placesProfile.rating !== null && Number(placesProfile.rating) < thresholds.low_rating) {
            badges.push('Nota baixa');
        }
    }

    return (
        <Paper variant="outlined" sx={{ p: 1.5 }}>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
                <Typography variant="subtitle2">Perfil Google</Typography>
                <Stack direction="row" spacing={1}>
                    <Button
                        size="small"
                        component="a"
                        href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(razaoSocial)}`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        Abrir no Maps
                    </Button>
                    <Button size="small" variant="outlined" onClick={sync}>
                        {placesProfile === null ? 'Sincronizar' : 'Atualizar'}
                    </Button>
                </Stack>
            </Stack>

            {placesProfile !== null && (
                <Stack spacing={0.5} sx={{ mb: 1 }}>
                    <Typography variant="body2">
                        {placesProfile.rating !== null
                            ? `Nota ${placesProfile.rating} (${placesProfile.user_rating_count ?? 0} avaliações)`
                            : 'Sem avaliações no Google'}
                    </Typography>
                    {placesProfile.primary_type && (
                        <Typography variant="caption" color="text.secondary">
                            Categoria: {placesProfile.primary_type}
                        </Typography>
                    )}
                    {placesProfile.business_status && (
                        <Typography variant="caption" color="text.secondary">
                            {businessStatusLabels[placesProfile.business_status] ?? placesProfile.business_status}
                        </Typography>
                    )}
                </Stack>
            )}

            {badges.length > 0 && (
                <Stack direction="row" spacing={0.5} sx={{ flexWrap: 'wrap' }}>
                    {badges.map((badge) => (
                        <Chip key={badge} label={badge} size="small" color="warning" variant="outlined" />
                    ))}
                </Stack>
            )}
        </Paper>
    );
}
