import { useMemo, useState } from 'react';
import { requestPasswordReset } from '../services/auth.service';

function isValidEmail(email) {
    return email.trim() !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

/**
 * Own password reset request state and submission.
 *
 * @param {{ t: (key: string) => string }} options
 * @returns {{
 *   email: string,
 *   submitting: boolean,
 *   flash: string,
 *   error: string,
 *   isValid: boolean,
 *   invalidEmail: boolean,
 *   setEmail: (email: string) => void,
 *   submit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 * }}
 */
export function useForgotPasswordForm({ t }) {
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState('');
    const [error, setError] = useState('');
    const isValid = useMemo(() => isValidEmail(email), [email]);
    const invalidEmail = email.trim() !== '' && !isValid;

    const submit = async (event) => {
        event.preventDefault();

        if (!isValid) {
            return;
        }

        setSubmitting(true);
        setFlash('');
        setError('');

        try {
            const response = await requestPasswordReset({ email });
            setFlash(response.message ?? t('auth.forgotPasswordSent'));
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.forgotPasswordError'),
            );
        } finally {
            setSubmitting(false);
        }
    };

    return {
        email,
        submitting,
        flash,
        error,
        isValid,
        invalidEmail,
        setEmail,
        submit,
    };
}
