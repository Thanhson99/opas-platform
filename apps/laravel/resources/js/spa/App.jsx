import { Suspense, lazy, useEffect, useLayoutEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import LoadingState from './components/ui/LoadingState';
import { AuthProvider } from './features/auth/context/AuthContext';
import { LanguageProvider } from './features/i18n/context/LanguageContext';
import {
    applyAuthRouteDocumentClasses,
    isAuthRoutePath,
} from './features/auth/utils/authRouteClasses';

const AdminShell = lazy(() => import('./components/layout/AdminShell'));
const AppShell = lazy(() => import('./components/layout/AppShell'));
const AccountSettingsPage = lazy(() => import('./features/auth/pages/AccountSettingsPage'));
const LoginPage = lazy(() => import('./features/auth/pages/LoginPage'));
const ForgotPasswordPage = lazy(() => import('./features/auth/pages/ForgotPasswordPage'));
const RegisterPage = lazy(() => import('./features/auth/pages/RegisterPage'));
const ResetPasswordPage = lazy(() => import('./features/auth/pages/ResetPasswordPage'));
const VerifyEmailPage = lazy(() => import('./features/auth/pages/VerifyEmailPage'));
const DashboardPage = lazy(() => import('./features/dashboard/pages/DashboardPage'));
const AlertEditPage = lazy(() => import('./features/coins/pages/AlertEditPage'));
const AlertsPage = lazy(() => import('./features/coins/pages/AlertsPage'));
const CoinDetailPage = lazy(() => import('./features/coins/pages/CoinDetailPage'));
const CoinsPage = lazy(() => import('./features/coins/pages/CoinsPage'));
const KeywordsPage = lazy(() => import('./features/coins/pages/KeywordsPage'));
const StocksPage = lazy(() => import('./features/stocks/pages/StocksPage'));
const VideosPage = lazy(() => import('./features/video-automation/pages/VideosPage'));
const RealtimeTranslatePage = lazy(
    () => import('./features/realtime-translate/pages/RealtimeTranslatePage'),
);

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

function RouteLoadingState() {
    const { pathname } = useLocation();
    const isAuthRoute = isAuthRoutePath(pathname);

    useLayoutEffect(() => {
        return applyAuthRouteDocumentClasses(isAuthRoute, pathname === '/login');
    }, [isAuthRoute, pathname]);

    return <LoadingState />;
}

/**
 * Render the top-level SPA providers and route tree.
 */
export default function App() {
    return (
        <BrowserRouter>
            <FacebookRedirectHashCleanup />
            <LanguageProvider>
                <AuthProvider>
                    <Suspense fallback={<RouteLoadingState />}>
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
                                            <Route
                                                path="/account"
                                                element={<AccountSettingsPage />}
                                            />
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
                                            <Route
                                                path="/realtime-translate"
                                                element={<RealtimeTranslatePage />}
                                            />
                                            <Route path="*" element={<Navigate to="/" replace />} />
                                        </Routes>
                                    </AppShell>
                                }
                            />
                        </Routes>
                    </Suspense>
                </AuthProvider>
            </LanguageProvider>
        </BrowserRouter>
    );
}
