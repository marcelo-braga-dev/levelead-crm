import { LeadCardData } from '@/types';
import { useDraggable } from '@dnd-kit/core';
import { Card, CardContent, Checkbox, Chip, Stack, Typography } from '@mui/material';
import { leadScoreTier, leadScoreTierColors, leadScoreTierLabels } from '@/utils/leadScore';

export default function LeadCard({
    lead,
    onClick,
    selectable = false,
    selected = false,
    onToggleSelect,
}: {
    lead: LeadCardData;
    onClick: () => void;
    selectable?: boolean;
    selected?: boolean;
    onToggleSelect?: () => void;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: lead.id, disabled: selectable });
    const tier = leadScoreTier(lead.total_score);

    return (
        <Card
            ref={setNodeRef}
            {...(selectable ? {} : { ...listeners, ...attributes })}
            variant="outlined"
            sx={{ mb: 1, cursor: selectable ? 'pointer' : 'grab', opacity: isDragging ? 0.4 : 1 }}
            onClick={selectable ? onToggleSelect : onClick}
        >
            <CardContent sx={{ p: 1.5 }}>
                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <Stack direction="row" sx={{ alignItems: 'center', minWidth: 0 }}>
                        {selectable && (
                            <Checkbox
                                size="small"
                                checked={selected}
                                onChange={onToggleSelect}
                                onClick={(e) => e.stopPropagation()}
                                sx={{ p: 0.25, mr: 0.5 }}
                            />
                        )}
                        <Typography variant="subtitle2" noWrap>
                            {lead.company.nome_fantasia ?? lead.company.razao_social}
                        </Typography>
                    </Stack>
                    <Chip
                        label={leadScoreTierLabels[tier]}
                        color={leadScoreTierColors[tier]}
                        size="small"
                        sx={{ height: 20, fontSize: '0.7rem', ml: 1 }}
                    />
                </Stack>
                {lead.contact_name && (
                    <Typography variant="body2" color="text.secondary" noWrap>
                        {lead.contact_name}
                    </Typography>
                )}
                {lead.assigned_to && (
                    <Typography variant="caption" color="text.secondary">
                        {lead.assigned_to.name}
                    </Typography>
                )}
            </CardContent>
        </Card>
    );
}
