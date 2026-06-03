import { useMemo, useState } from 'react';
import { resendEmailVerification, verifyEmail } from '../services/auth.service';

function resolveVerificationStatusMessage(status, t) {
    if (status === 'verified') {
        return t('auth.verifyEmailVerified');
    }

    if (status === 'already-verified') {
        return t('auth.verifyEmailAlreadyVerified');
    }

    if (status === 'expired') {
        return t('auth.verifyEmailExpired');
    }

    if (status === 'invalid') {
        return t('auth.verifyEmailInvalid');
    }

    return t('auth.verifyEmailPending');
}

/**
 * Own email verification state, status text, and resend actions.
 *
 * @param {{ initialEmail: string, initialStatus: string, t: (key: string) => string }} options
 * @returns {{
 *   email: string,
 *   code: string,
 *   verifying: boolean,
 *   resending: boolean,
 *   flash: string,
 *   error: string,
 *   status: string,
 *   statusMessage: string,
 *   canVerify: boolean,
 *   canResend: boolean,
 *   setEmail: (email: string) => void,
 *   setCode: (code: string) => void,
 *   submit: (event: import('react').FormEvent<HTMLFormElement>) => Promise<void>,
 *   resendCode: () => Promise<void>,
 * }}
 */
export function useVerifyEmailForm({ initialEmail, initialStatus, t }) {
    const [email, setEmail] = useState(initialEmail);
    const [code, setCode] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [resending, setResending] = useState(false);
    const [flash, setFlash] = useState('');
    const [error, setError] = useState('');
    const [status, setStatus] = useState(initialStatus);

    const statusMessage = useMemo(() => resolveVerificationStatusMessage(status, t), [status, t]);
    const canVerify = !verifying && email.trim() !== '' && code.trim().length === 6;
    const canResend = !resending && email.trim() !== '';

    const submit = async (event) => {
        event.preventDefault();
        setVerifying(true);
        setFlash('');
        setError('');

        try {
            const response = await verifyEmail({ email, code });
            setStatus(response?.meta?.status ?? 'verified');
            setFlash(response.message ?? t('auth.verifyEmailVerified'));
        } catch (requestError) {
            setStatus(requestError?.response?.data?.meta?.status ?? 'invalid');
            setError(
                requestError?.response?.data?.errors?.code?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.verifyEmailError'),
            );
        } finally {
            setVerifying(false);
        }
    };

    const resendCode = async () => {
        setResending(true);
        setFlash('');
        setError('');

        try {
            const response = await resendEmailVerification({ email });
            setStatus('pending');
            setFlash(response.message ?? t('auth.verifyEmailResent'));
        } catch (requestError) {
            setError(
                requestError?.response?.data?.errors?.email?.[0] ||
                    requestError?.response?.data?.message ||
                    t('auth.verifyEmailResendError'),
            );
        } finally {
            setResending(false);
        }
    };

    return {
        email,
        code,
        verifying,
        resending,
        flash,
        error,
        status,
        statusMessage,
        canVerify,
        canResend,
        setEmail,
        setCode,
        submit,
        resendCode,
    };
}
