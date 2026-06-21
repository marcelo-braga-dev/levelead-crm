import { Box, TextField, Typography } from '@mui/material';

const HEX_REGEX = /^#[0-9A-Fa-f]{6}$/;

export default function ColorPickerField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <Box>
            <Typography variant="subtitle2" sx={{ mb: 1 }}>
                {label}
            </Typography>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                <Box
                    component="input"
                    type="color"
                    value={HEX_REGEX.test(value) ? value : '#000000'}
                    onChange={(e) => onChange((e.target as HTMLInputElement).value.toUpperCase())}
                    sx={{
                        width: 48,
                        height: 40,
                        border: '1px solid',
                        borderColor: 'divider',
                        borderRadius: 1,
                        cursor: 'pointer',
                        p: 0.5,
                    }}
                />
                <TextField
                    size="small"
                    value={value}
                    onChange={(e) => onChange(e.target.value.toUpperCase())}
                    error={Boolean(error)}
                    helperText={error}
                    sx={{ maxWidth: 160 }}
                />
            </Box>
        </Box>
    );
}
