import { useEffect, useMemo, useState } from 'react';

function isInvalidEmail(email) {
    return email.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

/**
 * Own login form state, validation, flash messages, and submit behavior.
 *
 * @param {{
 *   location: import('react-router-dom').Location,
 *   login: (payload: { email: string, password: string }) => Promise<void>,
 *   navigate: (to: string, options?: Record<string, unknown>) => void,
 *   t: (key: string) => string,
 * }} options
 * @returns {{
 *   form: { email: string, password: string },
 *   fieldErrors: Record<string, Array<string>>,
 *   flash: string,
 *   error: string,
 *   invalidEmail: boolean,
 *   canSubmit: boolean,
 *   submitting: boolean,
 *   setForm: import('react').Dispatch<import('react').SetStateAction<{ email: string, password: string }>>,
 *   submit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }}
 */
export function useLoginForm({ location, login, navigate, t }) {
    const [form, setForm] = useState({
        email: '',
        password: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [flash, setFlash] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    const from = location.state?.from?.pathname || '/';

    useEffect(() => {
        const params = new URLSearchParams(location.search);
        const authError = params.get('auth_error');
        const reset = params.get('reset');

        setFlash('');

        if (authError) {
            setError(authError);
        }

        if (reset === 'success') {
            setError('');
            setFlash(t('auth.resetPasswordSuccess'));
        }
    }, [location.search, t]);

    const invalidEmail = isInvalidEmail(form.email);
    const canSubmit = useMemo(
        () =>
            form.email.trim() !== '' && !invalidEmail && form.password.trim() !== '' && !submitting,
        [form.email, form.password, invalidEmail, submitting],
    );

    const submit = async (event) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        setSubmitting(true);
        setError('');
        setFieldErrors({});

        try {
            await login(form);
            navigate(from, { replace: true });
        } catch (requestError) {
            if (requestError?.response?.data?.meta?.verification_required) {
                const email = requestError.response.data.meta.email ?? form.email;
                navigate(`/verify-email?email=${encodeURIComponent(email)}&status=pending`, {
                    replace: true,
                });
                return;
            }

            setFieldErrors(requestError?.response?.data?.errors ?? {});
            setError(requestError?.response?.data?.message || t('auth.loginError'));
        } finally {
            setSubmitting(false);
        }
    };

    return {
        form,
        fieldErrors,
        flash,
        error,
        invalidEmail,
        canSubmit,
        submitting,
        setForm,
        submit,
    };
}
