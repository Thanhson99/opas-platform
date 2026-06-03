import AuthPageShell from '../components/AuthPageShell';
import LoginPageContainer from '../components/LoginPageContainer';

/**
 * Render the login route in the auth shell.
 *
 * @returns {import('react').JSX.Element}
 */
export default function LoginPage() {
    return (
        <AuthPageShell>
            <LoginPageContainer />
        </AuthPageShell>
    );
}
