import { useLayoutEffect } from 'react';
import { useLocation } from 'react-router-dom';
import LanguageSelect from '../../../components/layout/LanguageSelect';
import { applyAuthRouteDocumentClasses } from '../utils/authRouteClasses';
import '../../../../../scss/modules/_auth-pages.scss';

/**
 * Render the shared auth screen frame.
 *
 * @param {{ children: import('react').ReactNode }} props
 * @returns {import('react').JSX.Element}
 */
export default function AuthPageShell({ children }) {
    const { pathname } = useLocation();
    const isLoginScreen = pathname === '/login';
    const screenClassName = `app-auth-screen ${
        isLoginScreen ? 'app-auth-screen--login' : 'app-auth-screen--scrollable'
    }`;

    useLayoutEffect(() => {
        return applyAuthRouteDocumentClasses(true, isLoginScreen);
    }, [isLoginScreen]);

    return (
        <>
            <div className="app-auth-floating-language">
                <LanguageSelect />
            </div>
            <div className={screenClassName}>
                <section className="app-auth-layout">{children}</section>
            </div>
        </>
    );
}
