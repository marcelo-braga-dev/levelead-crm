import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import ImportConfirmStep, { ImportBatch } from '@/Pages/Companies/Partials/ImportConfirmStep';
import ImportMappingStep from '@/Pages/Companies/Partials/ImportMappingStep';
import ImportUploadStep from '@/Pages/Companies/Partials/ImportUploadStep';
import { Head } from '@inertiajs/react';
import { Step, StepLabel, Stepper } from '@mui/material';

type ImportStep = 'upload' | 'mapping' | 'confirm';

const steps: { key: ImportStep; label: string }[] = [
    { key: 'upload', label: 'Enviar arquivo' },
    { key: 'mapping', label: 'Mapear colunas' },
    { key: 'confirm', label: 'Confirmar e processar' },
];

export default function CompaniesImport(props: {
    step: ImportStep;
    batch?: ImportBatch;
    header?: string[];
    suggestedMapping?: Record<string, string | null>;
    targetFields?: string[];
    profiles?: { id: number; name: string; column_mappings: { source_column_label: string; target_field: string }[] }[];
    totalRows?: number;
    mapping?: Record<string, string | null> | null;
}) {
    const activeStep = steps.findIndex((step) => step.key === props.step);

    return (
        <MuiAuthenticatedLayout title="Importar Empresas via CSV">
            <Head title="Importar CSV" />

            <Stepper activeStep={activeStep} sx={{ mb: 3 }}>
                {steps.map((step) => (
                    <Step key={step.key}>
                        <StepLabel>{step.label}</StepLabel>
                    </Step>
                ))}
            </Stepper>

            {props.step === 'upload' && <ImportUploadStep />}

            {props.step === 'mapping' && props.batch && (
                <ImportMappingStep
                    batchId={props.batch.id}
                    header={props.header ?? []}
                    suggestedMapping={props.suggestedMapping ?? {}}
                    targetFields={props.targetFields ?? []}
                    profiles={props.profiles ?? []}
                    totalRows={props.totalRows ?? 0}
                />
            )}

            {props.step === 'confirm' && props.batch && (
                <ImportConfirmStep
                    batch={props.batch}
                    mapping={props.mapping ?? null}
                    totalRows={props.totalRows ?? 0}
                />
            )}
        </MuiAuthenticatedLayout>
    );
}
