# Auth Provider Rules

## Purpose

Apply these rules whenever a task touches login methods, OAuth providers, provider admin screens, or verification policy.

## Provider Resolution Rules

- Provider lists must resolve dynamically from backend state, not from hardcoded frontend arrays.
- Provider readiness checks belong in auth provider drivers or services, not in controllers.
- Provider-specific OAuth logic should live in provider drivers or dedicated auth services.
- Email/password flow, OAuth flow, verification flow, and provider config flow should stay separated by responsibility.

## Config And Exposure Rules

- Callback URLs exposed to admins or frontend should be backend-generated.
- Secret provider config must not be returned in plaintext responses.
- Sensitive provider credentials must stay encrypted at rest.
- Frontend should receive only safe metadata and readiness state.

## Admin Management Rules

- Admin provider settings endpoints must be restricted to authorized admins.
- Invalid provider configuration must be rejected before a provider can become active.
- If the system has a baseline sign-in method, protect it with an explicit business rule and corresponding tests.
- Public login screens should show only providers that are active, ready, and intended for public visibility.
- Provider enablement rules should be enforced on the backend even if the frontend also prevents unsafe actions.
- Provider readiness and provider visibility are separate concerns and must not be conflated.
