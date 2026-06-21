import { useColorMode } from '@/Contexts/ThemeContext';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import BusinessIcon from '@mui/icons-material/Business';
import DarkModeIcon from '@mui/icons-material/DarkMode';
import DashboardIcon from '@mui/icons-material/Dashboard';
import GroupsIcon from '@mui/icons-material/Groups';
import HistoryIcon from '@mui/icons-material/History';
import Inventory2Icon from '@mui/icons-material/Inventory2';
import LightModeIcon from '@mui/icons-material/LightMode';
import LogoutIcon from '@mui/icons-material/Logout';
import NotificationsIcon from '@mui/icons-material/Notifications';
import PaletteIcon from '@mui/icons-material/Palette';
import RuleIcon from '@mui/icons-material/Rule';
import SettingsIcon from '@mui/icons-material/Settings';
import UploadFileIcon from '@mui/icons-material/UploadFile';
import ViewKanbanIcon from '@mui/icons-material/ViewKanban';
import PeopleIcon from '@mui/icons-material/People';
import {
    AppBar,
    Avatar,
    Badge,
    Box,
    Divider,
    Drawer,
    IconButton,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    Menu,
    MenuItem,
    Snackbar,
    Stack,
    Toolbar,
    Tooltip,
    Typography,
} from '@mui/material';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

const drawerWidth = 250;

const navItems = [
    { label: 'Painel', route: 'dashboard', icon: <DashboardIcon /> },
    { label: 'Kanban', route: 'kanban.board', icon: <ViewKanbanIcon /> },
    { label: 'Empresas', route: 'companies.index', icon: <BusinessIcon /> },
    { label: 'Importar CSV', route: 'companies.import', icon: <UploadFileIcon /> },
];

const managerNavItems = [
    { label: 'Auditoria', route: 'admin.audit.index', icon: <HistoryIcon /> },
    { label: 'Produtos', route: 'admin.products.index', icon: <Inventory2Icon /> },
];

const adminOnlyNavItems = [
    { label: 'Aparência', route: 'admin.appearance.edit', icon: <PaletteIcon /> },
    { label: 'Usuários', route: 'admin.users.index', icon: <PeopleIcon /> },
    { label: 'Equipes', route: 'admin.teams.index', icon: <GroupsIcon /> },
    { label: 'Regras', route: 'admin.rules.index', icon: <RuleIcon /> },
    { label: 'Configurações', route: 'admin.settings.edit', icon: <SettingsIcon /> },
];

export default function MuiAuthenticatedLayout({
    title,
    children,
}: PropsWithChildren<{ title?: ReactNode }>) {
    const { auth, flash, unreadNotifications } = usePage<PageProps>().props;
    const { mode, toggleMode } = useColorMode();
    const user = auth.user;
    const isAdmin = user.role === 'admin';
    const canSeeManagerNav = isAdmin || user.role === 'manager';
    const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);
    const [notificationsAnchorEl, setNotificationsAnchorEl] = useState<HTMLElement | null>(null);
    const [statusOpen, setStatusOpen] = useState(false);

    useEffect(() => {
        if (flash.status) {
            setStatusOpen(true);
        }
    }, [flash.status]);

    function markNotificationAsRead(id: string) {
        router.post(route('notifications.read', id), {}, { preserveScroll: true });
    }

    return (
        <Box sx={{ display: 'flex' }}>
            <AppBar
                position="fixed"
                sx={{
                    zIndex: (theme) => theme.zIndex.drawer + 1,
                    backgroundImage: (theme) =>
                        `linear-gradient(115deg, ${theme.palette.primary.dark ?? theme.palette.primary.main} 0%, ${theme.palette.primary.main} 55%, ${theme.palette.secondary.main} 130%)`,
                    color: '#fff',
                    boxShadow: (theme) => `0 4px 20px -6px ${theme.palette.primary.main}66`,
                }}
            >
                <Toolbar sx={{ gap: 1.5 }}>
                    <Typography variant="h6" noWrap sx={{ flexGrow: 1, fontWeight: 700 }}>
                        {title ?? 'LeveLead CRM'}
                    </Typography>

                    <Typography variant="body2" sx={{ display: { xs: 'none', sm: 'block' }, opacity: 0.85 }}>
                        {user.name}
                    </Typography>

                    <Tooltip title={mode === 'light' ? 'Ativar modo escuro' : 'Ativar modo claro'}>
                        <IconButton onClick={toggleMode} sx={{ color: 'inherit' }}>
                            {mode === 'light' ? <DarkModeIcon /> : <LightModeIcon />}
                        </IconButton>
                    </Tooltip>

                    {canSeeManagerNav && (
                        <>
                            <IconButton onClick={(event) => setNotificationsAnchorEl(event.currentTarget)} sx={{ color: 'inherit' }}>
                                <Badge badgeContent={unreadNotifications.length} color="error">
                                    <NotificationsIcon />
                                </Badge>
                            </IconButton>
                            <Menu
                                anchorEl={notificationsAnchorEl}
                                open={Boolean(notificationsAnchorEl)}
                                onClose={() => setNotificationsAnchorEl(null)}
                            >
                                {unreadNotifications.length === 0 && (
                                    <MenuItem disabled>Nenhuma notificação nova</MenuItem>
                                )}
                                {unreadNotifications.map((notification) => (
                                    <MenuItem
                                        key={notification.id}
                                        onClick={() => markNotificationAsRead(notification.id)}
                                        sx={{ whiteSpace: 'normal', maxWidth: 320 }}
                                    >
                                        {notification.data.label} — {notification.data.company_name}
                                    </MenuItem>
                                ))}
                            </Menu>
                        </>
                    )}

                    <Avatar
                        sx={{
                            width: 34,
                            height: 34,
                            cursor: 'pointer',
                            bgcolor: 'rgba(255,255,255,0.18)',
                            border: '1.5px solid rgba(255,255,255,0.55)',
                            fontSize: '0.9rem',
                            fontWeight: 700,
                        }}
                        onClick={(event) => setAnchorEl(event.currentTarget)}
                    >
                        {user.name.charAt(0).toUpperCase()}
                    </Avatar>

                    <Menu anchorEl={anchorEl} open={Boolean(anchorEl)} onClose={() => setAnchorEl(null)}>
                        <MenuItem component={Link} href={route('profile.edit')}>
                            Perfil
                        </MenuItem>
                        <MenuItem onClick={() => router.post(route('logout'))}>
                            <ListItemIcon>
                                <LogoutIcon fontSize="small" />
                            </ListItemIcon>
                            Sair
                        </MenuItem>
                    </Menu>
                </Toolbar>
            </AppBar>

            <Drawer
                variant="permanent"
                sx={{
                    width: drawerWidth,
                    flexShrink: 0,
                    [`& .MuiDrawer-paper`]: { width: drawerWidth, boxSizing: 'border-box' },
                }}
            >
                <Toolbar />
                <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', px: 2.5, py: 2.5 }}>
                    <Box
                        sx={{
                            width: 38,
                            height: 38,
                            borderRadius: 2.5,
                            backgroundImage: (theme) =>
                                `linear-gradient(135deg, ${theme.palette.primary.main}, ${theme.palette.secondary.main})`,
                            color: '#fff',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontWeight: 700,
                            boxShadow: (theme) => `0 6px 14px -4px ${theme.palette.primary.main}80`,
                        }}
                    >
                        L
                    </Box>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700 }} noWrap>
                        LeveLead CRM
                    </Typography>
                </Stack>
                <Divider />
                <Box sx={{ overflow: 'auto', flexGrow: 1, py: 1 }}>
                    <List sx={{ px: 1.5 }}>
                        {navItems.map((item) => (
                            <ListItem key={item.route} disablePadding sx={{ mb: 0.5 }}>
                                <ListItemButton
                                    component={Link}
                                    href={route(item.route)}
                                    selected={route().current(item.route)}
                                    sx={{ borderRadius: 2 }}
                                >
                                    <ListItemIcon>{item.icon}</ListItemIcon>
                                    <ListItemText primary={item.label} />
                                </ListItemButton>
                            </ListItem>
                        ))}
                        {canSeeManagerNav &&
                            managerNavItems.map((item) => (
                                <ListItem key={item.route} disablePadding sx={{ mb: 0.5 }}>
                                    <ListItemButton
                                        component={Link}
                                        href={route(item.route)}
                                        selected={route().current(item.route)}
                                        sx={{ borderRadius: 2 }}
                                    >
                                        <ListItemIcon>{item.icon}</ListItemIcon>
                                        <ListItemText primary={item.label} />
                                    </ListItemButton>
                                </ListItem>
                            ))}
                        {isAdmin &&
                            adminOnlyNavItems.map((item) => (
                                <ListItem key={item.route} disablePadding sx={{ mb: 0.5 }}>
                                    <ListItemButton
                                        component={Link}
                                        href={route(item.route)}
                                        selected={route().current(item.route)}
                                        sx={{ borderRadius: 2 }}
                                    >
                                        <ListItemIcon>{item.icon}</ListItemIcon>
                                        <ListItemText primary={item.label} />
                                    </ListItemButton>
                                </ListItem>
                            ))}
                    </List>
                </Box>
            </Drawer>

            <Box component="main" sx={{ flexGrow: 1, p: 3, width: { sm: `calc(100% - ${drawerWidth}px)` } }}>
                <Toolbar />
                {children}
            </Box>

            <Snackbar
                open={statusOpen}
                autoHideDuration={4000}
                onClose={() => setStatusOpen(false)}
                message={flash.status}
            />
        </Box>
    );
}
