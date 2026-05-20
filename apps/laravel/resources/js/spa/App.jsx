import { useEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import AdminShell from './components/layout/AdminShell';
import AppShell from './components/layout/AppShell';
import { AuthProvider } from './features/auth/context/AuthContext';
import AccountSettingsPage from './features/auth/pages/AccountSettingsPage';
import LoginPage from './features/auth/pages/LoginPage';
import ForgotPasswordPage from './features/auth/pages/ForgotPasswordPage';
import RegisterPage from './features/auth/pages/RegisterPage';
import ResetPasswordPage from './features/auth/pages/ResetPasswordPage';
import VerifyEmailPage from './features/auth/pages/VerifyEmailPage';
import DashboardPage from './features/dashboard/pages/DashboardPage';
import AlertEditPage from './features/coins/pages/AlertEditPage';
import AlertsPage from './features/coins/pages/AlertsPage';
import CoinDetailPage from './features/coins/pages/CoinDetailPage';
import CoinsPage from './features/coins/pages/CoinsPage';
import KeywordsPage from './features/coins/pages/KeywordsPage';
import StocksPage from './features/stocks/pages/StocksPage';
import VideosPage from './features/video-automation/pages/VideosPage';
import { LanguageProvider } from './features/i18n/context/LanguageContext';

/**
 * Root SPA router.
 *
 * Web routes in Laravel now point to a single Blade entry,
 * while React Router handles screen-level navigation here.
 */
function FacebookRedirectHashCleanup() {
    useEffect(() => {
        if (window.location.hash !== '#_=_') {
            return;
        }

        const cleanUrl = `${window.location.pathname}${window.location.search}`;
        window.history.replaceState(null, document.title, cleanUrl);
    }, []);

    return null;
}

export default function App() {
    return (
        <BrowserRouter>
            <FacebookRedirectHashCleanup />
            <LanguageProvider>
                <AuthProvider>
                    <Routes>
                        <Route path="/login" element={<LoginPage />} />
                        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                        <Route path="/register" element={<RegisterPage />} />
                        <Route path="/reset-password/:token" element={<ResetPasswordPage />} />
                        <Route path="/verify-email" element={<VerifyEmailPage />} />
                        <Route path="/admin/*" element={<AdminShell />} />
                        <Route
                            path="*"
                            element={
                                <AppShell>
                                    <Routes>
                                        <Route path="/" element={<DashboardPage />} />
                                        <Route path="/account" element={<AccountSettingsPage />} />
                                        <Route path="/coins" element={<CoinsPage />} />
                                        <Route
                                            path="/coins/show/:symbol"
                                            element={<CoinDetailPage />}
                                        />
                                        <Route
                                            path="/coins/feed-keywords"
                                            element={<KeywordsPage />}
                                        />
                                        <Route
                                            path="/coins/price-alert-settings"
                                            element={<AlertsPage />}
                                        />
                                        <Route
                                            path="/coins/price-alert-settings/:id/edit"
                                            element={<AlertEditPage />}
                                        />
                                        <Route path="/stocks" element={<StocksPage />} />
                                        <Route
                                            path="/video-automation/trending"
                                            element={<VideosPage />}
                                        />
                                        <Route path="*" element={<Navigate to="/" replace />} />
                                    </Routes>
                                </AppShell>
                            }
                        />
                    </Routes>
                </AuthProvider>
            </LanguageProvider>
        </BrowserRouter>
    );
}
