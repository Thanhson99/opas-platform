export function getPasswordChecks(password) {
    const value = String(password ?? '');

    return {
        minLength: value.length >= 8,
        hasLowercase: /[a-z]/.test(value),
        hasUppercase: /[A-Z]/.test(value),
        hasNumber: /\d/.test(value),
        hasSymbol: /[^A-Za-z0-9]/.test(value),
    };
}

export function isStrongPassword(password) {
    const checks = getPasswordChecks(password);

    return Object.values(checks).every(Boolean);
}

export function getMissingPasswordRuleKeys(password) {
    const checks = getPasswordChecks(password);

    return [
        !checks.minLength ? 'passwordRuleMinLength' : null,
        !checks.hasLowercase ? 'passwordRuleLowercase' : null,
        !checks.hasUppercase ? 'passwordRuleUppercase' : null,
        !checks.hasNumber ? 'passwordRuleNumber' : null,
        !checks.hasSymbol ? 'passwordRuleSymbol' : null,
    ].filter(Boolean);
}
