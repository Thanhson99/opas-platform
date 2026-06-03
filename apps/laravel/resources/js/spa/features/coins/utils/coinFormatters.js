const compactCurrency = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 2,
});

const money = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 3,
});

/**
 * Format a market number as compact USD.
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatCompactUsd(value) {
    return `$${compactCurrency.format(Number(value ?? 0))}`;
}

/**
 * Format a market number as USD.
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatUsd(value) {
    return `$${money.format(Number(value ?? 0))}`;
}

/**
 * Format a percent value for coin movement.
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatPercent(value) {
    return `${Number(value ?? 0).toFixed(2)}%`;
}
