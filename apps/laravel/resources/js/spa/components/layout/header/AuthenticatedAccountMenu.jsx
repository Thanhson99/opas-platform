import { Link } from 'react-router-dom';
import AppIcon from '../../icons/AppIcon';

/**
 * Render the authenticated account trigger and dropdown menu.
 *
 * @param {{
 *   accountMenuRef: import('react').RefObject<HTMLDivElement>,
 *   user: { name: string, role_label?: string },
 *   open: boolean,
 *   onToggle: () => void,
 *   onClose: () => void,
 *   onLogout: () => void,
 *   t: (key: string) => string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthenticatedAccountMenu({
    accountMenuRef,
    user,
    open,
    onToggle,
    onClose,
    onLogout,
    t,
}) {
    const initials = user.name.slice(0, 2).toUpperCase();

    return (
        <div ref={accountMenuRef} className="opas-account opas-account--menu">
            <button
                type="button"
                className="opas-account__trigger"
                aria-expanded={open}
                aria-haspopup="menu"
                aria-label={t('header.accountMenu')}
                onClick={onToggle}
            >
                <span className="opas-account__avatar">{initials}</span>
                <div className="opas-account__meta">
                    <strong>{user.name}</strong>
                    {user.role_label ? <span>{user.role_label}</span> : null}
                </div>
                <AppIcon
                    name="chevron-down"
                    className={`opas-account__chevron ${open ? 'is-open' : ''}`}
                />
            </button>
            {open ? (
                <div className="opas-account__dropdown" role="menu">
                    <Link
                        to="/account"
                        className="opas-account__menu-link"
                        role="menuitem"
                        onClick={onClose}
                    >
                        <AppIcon name="users" />
                        <span>{t('header.accountOverview')}</span>
                    </Link>
                    <button
                        type="button"
                        className="opas-account__menu-link"
                        role="menuitem"
                        onClick={onLogout}
                    >
                        <AppIcon name="logout" />
                        <span>{t('common.logout')}</span>
                    </button>
                </div>
            ) : null}
        </div>
    );
}
