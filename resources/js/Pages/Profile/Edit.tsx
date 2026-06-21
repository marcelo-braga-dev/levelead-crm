import MuiAuthenticatedLayout from '@/Layouts/MuiAuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Paper, Stack } from '@mui/material';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <MuiAuthenticatedLayout title="Perfil">
            <Head title="Perfil" />

            <Stack spacing={3} sx={{ maxWidth: 720 }}>
                <Paper sx={{ p: 3 }}>
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <UpdatePasswordForm />
                </Paper>

                <Paper sx={{ p: 3 }}>
                    <DeleteUserForm />
                </Paper>
            </Stack>
        </MuiAuthenticatedLayout>
    );
}
