import { useLocation, useNavigate } from 'react-router-dom';
import { useLanguage } from '../../i18n/context/LanguageContext';
import { useAuth } from '../context/AuthContext';
import { getPasswordFormProvider } from '../lib/publicAuthProviders';
import {
    buildLoginProvidersViewModel,
    buildLoginRegistrationViewModel,
} from './loginPageViewModel.helpers';
import { useLoginForm } from './useLoginForm';

/**
 * @typedef {import('../lib/publicAuthProviders').PublicAuthProvider} PublicAuthProvider
 */

/**
 * @typedef {{
 *   canSubmit: boolean,
 *   error: string,
 *   fieldErrors: Record<string, Array<string>>,
 *   flash: string,
 *   form: { email: string, password: string },
 *   invalidEmail: boolean,
 *   submitting: boolean,
 *   setForm: import('react').Dispatch<import('react').SetStateAction<{ email: string, password: string }>>,
 *   submit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }} LoginFormViewModel
 */

/**
 * @typedef {{
 *   emailProvider: PublicAuthProvider | null,
 *   loginForm: LoginFormViewModel,
 *   providers: import('./loginPageViewModel.helpers').LoginProvidersViewModel,
 *   registration: import('./loginPageViewModel.helpers').LoginRegistrationViewModel,
 *   t: (key: string) => string,
 * }} LoginPageViewModel
 */

/**
 * Build the login page view model from auth state and route context.
 *
 * @returns {LoginPageViewModel}
 */
export function useLoginPageViewModel() {
    const navigate = useNavigate();
    const location = useLocation();
    const { login, loginProviders, registerProviders, providersLoading, providersError } =
        useAuth();
    const { t } = useLanguage();
    const emailProvider = getPasswordFormProvider(loginProviders);
    const loginForm = useLoginForm({ location, login, navigate, t });

    return {
        emailProvider,
        loginForm,
        providers: buildLoginProvidersViewModel({
            loginProviders,
            emailProvider,
            providersLoading,
            providersError,
        }),
        registration: buildLoginRegistrationViewModel(registerProviders),
        t,
    };
}
