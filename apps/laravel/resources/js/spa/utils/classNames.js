/**
 * Join conditional class names without adding a package dependency.
 *
 * @param {Array<string | false | null | undefined>} classNames
 * @returns {string}
 */
export function joinClassNames(...classNames) {
    return classNames.filter(Boolean).join(' ');
}
