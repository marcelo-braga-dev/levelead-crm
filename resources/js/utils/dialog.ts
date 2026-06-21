/**
 * MUI centraliza o Dialog verticalmente por padrão — como o conteúdo muda de altura entre abas
 * (LeadDetailDialog) ou entre o conteúdo condicional de cada motivo de transição
 * (StageTransitionDialog), isso fazia o diálogo "saltar" de posição a cada clique. Fixar o topo
 * resolve sem precisar de altura fixa no conteúdo.
 */
export const topAlignedDialogSlotProps = {
    container: { sx: { alignItems: 'flex-start' } },
    paper: { sx: { mt: 6, mb: 6 } },
};
