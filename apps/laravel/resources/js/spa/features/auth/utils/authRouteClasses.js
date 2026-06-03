const AUTH_ROUTE_CLASS = 'app-auth-route';
const AUTH_LOGIN_ROUTE_CLASS = 'app-auth-route--login';

export function isAuthRoutePath(pathname) {
    return (
        pathname === '/login' ||
        pathname === '/forgot-password' ||
        pathname === '/register' ||
        pathname.startsWith('/reset-password') ||
        pathname === '/verify-email'
    );
}

export function applyAuthRouteDocumentClasses(isAuthRoute, isLoginRoute) {
    const root = document.documentElement;
    const body = document.body;

    if (isAuthRoute) {
        root.classList.add(AUTH_ROUTE_CLASS);
        body.classList.add(AUTH_ROUTE_CLASS);
    }

    if (isLoginRoute) {
        root.classList.add(AUTH_LOGIN_ROUTE_CLASS);
        body.classList.add(AUTH_LOGIN_ROUTE_CLASS);
    }

    return () => {
        root.classList.remove(AUTH_ROUTE_CLASS, AUTH_LOGIN_ROUTE_CLASS);
        body.classList.remove(AUTH_ROUTE_CLASS, AUTH_LOGIN_ROUTE_CLASS);
    };
}
