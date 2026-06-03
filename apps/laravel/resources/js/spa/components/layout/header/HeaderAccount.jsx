import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import AuthenticatedAccountMenu from './AuthenticatedAccountMenu';
import GuestAccountActions from './GuestAccountActions';

/**
 * Render loading, authenticated, or guest account controls inside the workspace header.
 *
 * @param {{
 *   loading: boolean,
 *   user: { name: string, role_label?: string } | null,
 *   registerProviders: Array<object>,
 *   logout: () => Promise<void>,
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function HeaderAccount({ loading, user, registerProviders, logout, t }) {
    const navigate = useNavigate();
    const accountMenuRef = useRef(null);
    const [accountMenuOpen, setAccountMenuOpen] = useState(false);

    useEffect(() => {
        if (!accountMenuOpen) {
            return undefined;
        }

        const handleDocumentMouseDown = (event) => {
            if (accountMenuRef.current?.contains(event.target)) {
                return;
            }

            setAccountMenuOpen(false);
        };

        const handleDocumentKeyDown = (event) => {
            if (event.key === 'Escape') {
                setAccountMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', handleDocumentMouseDown);
        document.addEventListener('keydown', handleDocumentKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleDocumentMouseDown);
            document.removeEventListener('keydown', handleDocumentKeyDown);
        };
    }, [accountMenuOpen]);

    const handleLogout = async () => {
        setAccountMenuOpen(false);
        await logout();
        navigate('/', { replace: true });
    };

    if (loading) {
        return <div className="opas-account opas-account--muted">{t('common.loadingAccount')}</div>;
    }

    if (user) {
        return (
            <AuthenticatedAccountMenu
                accountMenuRef={accountMenuRef}
                user={user}
                open={accountMenuOpen}
                onToggle={() => setAccountMenuOpen((value) => !value)}
                onClose={() => setAccountMenuOpen(false)}
                onLogout={handleLogout}
                t={t}
            />
        );
    }

    return <GuestAccountActions registerProviders={registerProviders} t={t} />;
}
