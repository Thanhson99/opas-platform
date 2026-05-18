import { availableAppIcons, hasAppIcon } from '../../../../components/icons/AppIcon';

const staticFieldMeta = {
    client_id: { key: 'client_id', span: 'half' },
    redirect_uri: { key: 'redirect_uri', span: 'full' },
    client_secret: { key: 'client_secret', span: 'full' },
    button_text: { key: 'button_text', span: 'half' },
    scopes: { key: 'scopes', span: 'full' },
};

const staticBaseMeta = {
    display_name: { key: 'display_name', span: 'half' },
    icon: { key: 'icon', span: 'half' },
    sort_order: { key: 'sort_order', span: 'half' },
};

function toTextValue(value) {
    if (value === null || value === undefined) {
        return '';
    }

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    return String(value);
}

function sortEntries(input) {
    return Object.fromEntries(
        Object.entries(input).sort(([left], [right]) => left.localeCompare(right)),
    );
}

function normalizeFormSnapshot(form) {
    return {
        enabled: Boolean(form.enabled),
        display_name: String(form.display_name ?? '').trim(),
        icon: String(form.icon ?? '').trim(),
        sort_order: String(form.sort_order ?? '').trim(),
        visibility: String(form.visibility ?? 'public').trim(),
        email_verification_mode: String(form.email_verification_mode ?? '').trim(),
        public_config: sortEntries(
            Object.fromEntries(
                Object.entries(form.public_config ?? {}).map(([key, value]) => [
                    key,
                    toTextValue(value).trim(),
                ]),
            ),
        ),
    };
}

export function buildInitialForm(provider) {
    const requiredPublicKeys = provider.required_public_keys ?? [];
    const requiredSecretKeys = provider.required_secret_keys ?? [];
    const publicConfig = provider.public_config ?? {};

    const publicConfigFields = Object.fromEntries(
        requiredPublicKeys.map((key) => [key, toTextValue(publicConfig[key])]),
    );

    if (!Object.prototype.hasOwnProperty.call(publicConfigFields, 'button_text')) {
        publicConfigFields.button_text = toTextValue(publicConfig.button_text);
    }

    return {
        enabled: Boolean(provider.enabled),
        display_name: provider.display_name ?? '',
        icon: provider.icon ?? '',
        sort_order: String(provider.sort_order ?? 0),
        visibility: provider.visibility ?? 'public',
        email_verification_mode:
            provider.key === 'email' ? 'required' : (provider.email_verification_mode ?? ''),
        public_config: publicConfigFields,
        secret_config: Object.fromEntries(requiredSecretKeys.map((key) => [key, ''])),
    };
}

export function buildFieldMeta(t, field, options = {}) {
    const meta = staticFieldMeta[field];

    if (!meta) {
        return {
            label: field,
            description: t('adminAuth.fields.fallbackDescription'),
            placeholder: `${t('adminAuth.fields.fallbackPlaceholder')} ${field}`,
            span: 'half',
        };
    }

    const placeholder =
        field === 'redirect_uri' && options.callbackUrl
            ? options.callbackUrl
            : t(`adminAuth.fields.${meta.key}.placeholder`);

    return {
        label: t(`adminAuth.fields.${meta.key}.label`),
        description: t(`adminAuth.fields.${meta.key}.description`),
        placeholder,
        span: meta.span,
    };
}

export function buildBaseMeta(t, field) {
    const meta = staticBaseMeta[field];

    return {
        label: t(`adminAuth.basic.${meta.key}.label`),
        description: t(`adminAuth.basic.${meta.key}.description`),
        placeholder: t(`adminAuth.basic.${meta.key}.placeholder`),
        span: meta.span,
    };
}

export function getProviderSummary(provider, t) {
    return t(`adminAuth.providers.${provider.key}.summary`) !==
        `adminAuth.providers.${provider.key}.summary`
        ? t(`adminAuth.providers.${provider.key}.summary`)
        : t('adminAuth.providers.default.summary');
}

export function getFieldIssues(provider, form, t) {
    const issues = {};

    if (!form.display_name.trim()) {
        issues.display_name = t('adminAuth.validation.displayNameRequired');
    }

    if (form.sort_order.trim() === '') {
        issues.sort_order = t('adminAuth.validation.sortOrderRequired');
    }

    if (!/^\d+$/.test(form.sort_order.trim() || '')) {
        issues.sort_order = t('adminAuth.validation.sortOrderInvalid');
    }

    const iconName = form.icon.trim();

    if (iconName !== '' && !hasAppIcon(iconName)) {
        issues.icon = t('adminAuth.validation.iconInvalid');
    }

    for (const key of provider.required_public_keys ?? []) {
        if (!String(form.public_config[key] ?? '').trim()) {
            issues[`public_config.${key}`] = t('adminAuth.validation.requiredField');
        }
    }

    for (const key of provider.required_secret_keys ?? []) {
        const hasStoredSecret = Boolean(provider.secret_status?.[key]);
        const hasInputSecret = Boolean(String(form.secret_config[key] ?? '').trim());

        // A stored secret remains valid until the admin intentionally rotates it.
        if (!hasStoredSecret && !hasInputSecret) {
            issues[`secret_config.${key}`] = t('adminAuth.validation.requiredField');
        }
    }

    const redirectUri = String(form.public_config.redirect_uri ?? '').trim();

    if (redirectUri !== '') {
        try {
            new URL(redirectUri);
        } catch {
            issues['public_config.redirect_uri'] = t('adminAuth.validation.redirectUriInvalid');
        }
    }

    return issues;
}

export function getAvailableIconNames() {
    return availableAppIcons;
}

export function flattenMessages(errors) {
    return Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter(Boolean);
}

export function buildProviderPayload(form) {
    const publicConfig = Object.fromEntries(
        Object.entries(form.public_config).filter(([, value]) => value !== ''),
    );

    if (publicConfig.scopes) {
        publicConfig.scopes = publicConfig.scopes
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);
    }

    const secretConfig = Object.fromEntries(
        Object.entries(form.secret_config).filter(([, value]) => value !== ''),
    );

    return {
        enabled: form.enabled,
        display_name: form.display_name,
        icon: form.icon || null,
        sort_order: Number.parseInt(form.sort_order, 10) || 0,
        visibility: form.visibility,
        email_verification_mode: form.email_verification_mode || null,
        public_config: publicConfig,
        secret_config: secretConfig,
    };
}

export function isProviderFormDirty(form, initialForm) {
    const hasSecretUpdates = Object.values(form.secret_config ?? {}).some(
        (value) => String(value ?? '').trim() !== '',
    );

    if (hasSecretUpdates) {
        return true;
    }

    return (
        JSON.stringify(normalizeFormSnapshot(form)) !==
        JSON.stringify(normalizeFormSnapshot(initialForm))
    );
}
