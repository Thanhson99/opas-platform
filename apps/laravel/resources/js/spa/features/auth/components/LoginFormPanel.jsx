import LoginForm from './LoginForm';

/**
 * @typedef {import('../hooks/useLoginPageViewModel').LoginFormViewModel} LoginFormViewModel
 */

/**
 * Bind the login form hook contract to the reusable form component.
 *
 * @param {{ loginForm: LoginFormViewModel, t: (key: string) => string }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoginFormPanel({ loginForm, t }) {
    return (
        <LoginForm
            canSubmit={loginForm.canSubmit}
            error={loginForm.error}
            fieldErrors={loginForm.fieldErrors}
            flash={loginForm.flash}
            form={loginForm.form}
            invalidEmail={loginForm.invalidEmail}
            submitting={loginForm.submitting}
            t={t}
            onFormChange={loginForm.setForm}
            onSubmit={loginForm.submit}
        />
    );
}
