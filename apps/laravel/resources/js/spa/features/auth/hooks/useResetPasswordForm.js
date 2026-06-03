import { useMemo, useState } from 'react';
import { getMissingPasswordRuleKeys, isStrongPassword } from '../lib/passwordValidation';
import { resetPassword } from '../services/auth.service';

function isValidEmail(email) {
    return email.trim() !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

/**
 * Own password reset completion state and validation.
 *
 * @param {{
 *   initialEmail: string,
 *   navigate: (to: string, options?: Record<string, unknown>) => void,
 *   token: string,
 *   t: (key: string) => string,
 * }} options
 * @returns {{
 *   form: { email: string, password: string, password_confirmation: string },
 *   submitting: boolean,
 *   error: string,
 *   weakPassword: boolean,
 *   missingPasswordRuleKeys: Array<string>,
 *   passwordMismatch: boolean,
 *   invalidEmail: boolean,
 *   canSubmit: boolean,
 *   setForm: import('react').Dispatch<import('react').SetStateAction<{ email: string, password: string, password_confirmation: string }>>,
 *   submit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }}
 */
export function useResetPasswordForm({ initialEmail, navigate, token, t }) {
    const [form, setForm] = useState({
        email: initialEmail,
        password: '',
        password_confirmation: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const weakPassword = form.password.trim() !== '' && !isStrongPassword(form.password);
    const invalidEmail = form.email.trim() !== '' && !isValidEmail(form.email);
    const missingPasswordRuleKeys = useMemo(
        () => getMissingPasswordRuleKeys(form.password),
        [form.password],
    );
    const passwordMismatch =
        form.password_confirmation.trim() !== '' && form.password !== form.password_confirmation;
    const canSubmit = useMemo(
        () =>
            isValidEmail(form.email) &&
            form.password.trim() !== '' &&
            isStrongPassword(form.password) &&
            form.password_confirmation.trim() !== '' &&
            !passwordMismatch &&
            !submitting,
        [form.email, form.password, form.password_confirmation, passwordMismatch, submitting],
    );

    const submit = async (event) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        setSubmitting(true);
        setError('');

        try {
            await resetPassword({ ...form, token });
            navigate('/login?reset=success', { replace: true });
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.resetPasswordError'),
            );
        } finally {
            setSubmitting(false);
        }
    };

    return {
        form,
        submitting,
        error,
        weakPassword,
        missingPasswordRuleKeys,
        passwordMismatch,
        invalidEmail,
        canSubmit,
        setForm,
        submit,
    };
}
