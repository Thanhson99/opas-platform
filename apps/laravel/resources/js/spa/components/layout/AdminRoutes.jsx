import { lazy } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

const AdminTelegramBotsPage = lazy(
    () => import('../../features/auto-coding/pages/admin/AdminTelegramBotsPage'),
);
const AuthProviderAdminPage = lazy(
    () => import('../../features/auth/pages/admin/AuthProviderAdminPage'),
);
const AuthProvidersDashboardPage = lazy(
    () => import('../../features/auth/pages/admin/AuthProvidersDashboardPage'),
);
const AdminUsersPage = lazy(() => import('../../features/auth/pages/admin/AdminUsersPage'));

/**
 * Render the admin-only route tree.
 *
 * @returns {import('react').JSX.Element}
 */
export default function AdminRoutes() {
    return (
        <Routes>
            <Route path="/users" element={<AdminUsersPage />} />
            <Route path="/auth/providers" element={<AuthProvidersDashboardPage />} />
            <Route path="/auth/providers/:key" element={<AuthProviderAdminPage />} />
            <Route path="/auto-coding/telegram-bots" element={<AdminTelegramBotsPage />} />
            <Route path="/auto-coding/telegram-bots/:key" element={<AdminTelegramBotsPage />} />
            <Route path="*" element={<Navigate to="/admin/users" replace />} />
        </Routes>
    );
}
