import { LeadCardData } from '@/types';
import { formatCurrency, formatPhone, formatRelativeDays, formatShortDate } from '@/utils/format';
import { leadScoreTier, leadScoreTierColors, leadScoreTierLabels } from '@/utils/leadScore';
import { useDraggable } from '@dnd-kit/core';
import AlarmIcon from '@mui/icons-material/Alarm';
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmpty';
import PersonOutlineIcon from '@mui/icons-material/PersonOutlineOutlined';
import PhoneIcon from '@mui/icons-material/Phone';
import SellIcon from '@mui/icons-material/Sell';
import WhatsAppIcon from '@mui/icons-material/WhatsApp';
import { Avatar, Card, CardContent, Checkbox, Chip, IconButton, Stack, Tooltip, Typography } from '@mui/material';
import { SyntheticEvent } from 'react';

function stopPropagation(event: SyntheticEvent) {
    event.stopPropagation();
}

export default function LeadCard({
    lead,
    onClick,
    accentColor,
    selectable = false,
    selected = false,
    onToggleSelect,
}: {
    lead: LeadCardData;
    onClick: () => void;
    accentColor: string;
    selectable?: boolean;
    selected?: boolean;
    onToggleSelect?: () => void;
}) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: lead.id, disabled: selectable });
    const tier = leadScoreTier(lead.total_score);

    const activeProposal = lead.proposals.find((proposal) => proposal.status === 'active');
    const nextFollowUp = lead.follow_ups.find((followUp) => followUp.status !== 'done');
    const lastInteraction = lead.interactions.find((interaction) => interaction.type !== 'system');

    return (
        <Card
            ref={setNodeRef}
            {...(selectable ? {} : { ...listeners, ...attributes })}
            variant="outlined"
            sx={{
                mb: 1,
                cursor: selectable ? 'pointer' : 'grab',
                opacity: isDragging ? 0.4 : 1,
                borderLeft: `4px solid ${accentColor}`,
                '&:hover': {
                    boxShadow: (theme) => `0 8px 18px -8px ${theme.palette.mode === 'light' ? '#1118271a' : '#000000aa'}`,
                    borderColor: 'divider',
                },
            }}
            onClick={selectable ? onToggleSelect : onClick}
        >
            <CardContent sx={{ p: 1.5, '&:last-child': { pb: 1.5 } }}>
                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <Stack direction="row" sx={{ alignItems: 'center', minWidth: 0 }}>
                        {selectable && (
                            <Checkbox
                                size="small"
                                checked={selected}
                                onChange={onToggleSelect}
                                onClick={stopPropagation}
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
                        sx={{ height: 20, fontSize: '0.7rem', ml: 1, flexShrink: 0 }}
                    />
                </Stack>

                {lead.contact_name && (
                    <Stack direction="row" sx={{ alignItems: 'center', mt: 0.25 }}>
                        <Typography variant="body2" color="text.secondary" noWrap sx={{ flexGrow: 1, minWidth: 0 }}>
                            {lead.contact_name}
                        </Typography>
                        {lead.contact_phone && (
                            <Tooltip title={`Ligar para ${formatPhone(lead.contact_phone)}`}>
                                <IconButton
                                    size="small"
                                    component="a"
                                    href={`tel:${lead.contact_phone}`}
                                    onPointerDown={stopPropagation}
                                    onClick={stopPropagation}
                                    sx={{ p: 0.4 }}
                                >
                                    <PhoneIcon sx={{ fontSize: 16 }} />
                                </IconButton>
                            </Tooltip>
                        )}
                        {lead.contact_whatsapp && (
                            <Tooltip title="Abrir WhatsApp">
                                <IconButton
                                    size="small"
                                    component="a"
                                    href={`https://wa.me/${lead.contact_whatsapp.replace(/\D/g, '')}`}
                                    target="_blank"
                                    rel="noopener"
                                    onPointerDown={stopPropagation}
                                    onClick={stopPropagation}
                                    sx={{ p: 0.4, color: '#25D366' }}
                                >
                                    <WhatsAppIcon sx={{ fontSize: 16 }} />
                                </IconButton>
                            </Tooltip>
                        )}
                    </Stack>
                )}

                {(activeProposal?.value || nextFollowUp) && (
                    <Stack direction="row" spacing={0.75} sx={{ mt: 1, flexWrap: 'wrap', rowGap: 0.5 }}>
                        {activeProposal?.value && (
                            <Chip
                                size="small"
                                variant="outlined"
                                color="success"
                                icon={<SellIcon sx={{ fontSize: 14 }} />}
                                label={formatCurrency(activeProposal.value)}
                                sx={{ height: 22, fontSize: '0.68rem' }}
                            />
                        )}
                        {nextFollowUp && (
                            <Tooltip title={`Acompanhamento agendado para ${formatShortDate(nextFollowUp.scheduled_at)}`}>
                                <Chip
                                    size="small"
                                    variant={nextFollowUp.status === 'overdue' ? 'filled' : 'outlined'}
                                    color={nextFollowUp.status === 'overdue' ? 'error' : 'default'}
                                    icon={<AlarmIcon sx={{ fontSize: 14 }} />}
                                    label={formatShortDate(nextFollowUp.scheduled_at)}
                                    sx={{ height: 22, fontSize: '0.68rem' }}
                                />
                            </Tooltip>
                        )}
                    </Stack>
                )}

                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mt: 1 }}>
                    {lead.assigned_to ? (
                        <Tooltip title={lead.assigned_to.name}>
                            <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center', minWidth: 0 }}>
                                <Avatar sx={{ width: 18, height: 18, fontSize: '0.6rem', bgcolor: 'primary.main' }}>
                                    {lead.assigned_to.name.charAt(0).toUpperCase()}
                                </Avatar>
                                <Typography variant="caption" color="text.secondary" noWrap>
                                    {lead.assigned_to.name.split(' ')[0]}
                                </Typography>
                            </Stack>
                        </Tooltip>
                    ) : (
                        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                            <PersonOutlineIcon sx={{ fontSize: 14, color: 'warning.main' }} />
                            <Typography variant="caption" color="warning.main">
                                Sem consultor
                            </Typography>
                        </Stack>
                    )}

                    <Tooltip title="Última interação registrada com o lead">
                        <Stack direction="row" spacing={0.4} sx={{ alignItems: 'center' }}>
                            <HourglassEmptyIcon
                                sx={{ fontSize: 13, color: lastInteraction ? 'text.secondary' : 'warning.main' }}
                            />
                            <Typography
                                variant="caption"
                                color={lastInteraction ? 'text.secondary' : 'warning.main'}
                                noWrap
                            >
                                {lastInteraction ? formatRelativeDays(lastInteraction.occurred_at) : 'sem contato'}
                            </Typography>
                        </Stack>
                    </Tooltip>
                </Stack>
            </CardContent>
        </Card>
    );
}
