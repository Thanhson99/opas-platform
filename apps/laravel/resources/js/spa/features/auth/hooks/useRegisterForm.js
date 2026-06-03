import { useMemo, useState } from 'react';
import { getMissingPasswordRuleKeys, isStrongPassword } from '../lib/passwordValidation';

function isInvalidEmail(email) {
    return email.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

/**
 * Own registration form state, validation, and submit behavior.
 *
 * @param {{
 *   navigate: (to: string, options?: Record<string, unknown>) => void,
 *   register: (payload: Record<string, string>) => Promise<Record<string, unknown>>,
 *   t: (key: string) => string,
 * }} options
 * @returns {{
 *   form: { name: string, email: string, password: string, password_confirmation: string },
 *   fieldErrors: Record<string, Array<string>>,
 *   error: string,
 *   weakPassword: boolean,
 *   missingPasswordRuleKeys: Array<string>,
 *   passwordMismatch: boolean,
 *   invalidEmail: boolean,
 *   canSubmit: boolean,
 *   submitting: boolean,
 *   setForm: import('react').Dispatch<import('react').SetStateAction<{ name: string, email: string, password: string, password_confirmation: string }>>,
 *   submit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }}
 */
export function useRegisterForm({ navigate, register, t }) {
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    const weakPassword = form.password.trim() !== '' && !isStrongPassword(form.password);
    const missingPasswordRuleKeys = useMemo(
        () => getMissingPasswordRuleKeys(form.password),
        [form.password],
    );
    const passwordMismatch =
        form.password_confirmation.trim() !== '' && form.password !== form.password_confirmation;
    const invalidEmail = isInvalidEmail(form.email);
    const canSubmit = useMemo(
        () =>
            form.name.trim() !== '' &&
            form.email.trim() !== '' &&
            !invalidEmail &&
            form.password.trim() !== '' &&
            isStrongPassword(form.password) &&
            form.password_confirmation.trim() !== '' &&
            !passwordMismatch &&
            !submitting,
        [
            form.email,
            form.name,
            form.password,
            form.password_confirmation,
            invalidEmail,
            passwordMismatch,
            submitting,
        ],
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
            const response = await register(form);
            const emailVerified = Boolean(response?.data?.email_verified);

            if (emailVerified) {
                navigate('/login', { replace: true });
                return;
            }

            navigate(`/verify-email?email=${encodeURIComponent(form.email)}&status=pending`, {
                replace: true,
            });
        } catch (requestError) {
            setFieldErrors(requestError?.response?.data?.errors ?? {});
            setError(requestError?.response?.data?.message || t('auth.registerError'));
        } finally {
            setSubmitting(false);
        }
    };

    return {
        form,
        fieldErrors,
        error,
        weakPassword,
        missingPasswordRuleKeys,
        passwordMismatch,
        invalidEmail,
        canSubmit,
        submitting,
        setForm,
        submit,
    };
}
