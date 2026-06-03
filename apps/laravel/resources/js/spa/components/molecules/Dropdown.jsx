import { memo, useCallback, useEffect, useState } from 'react';
import AppIcon from '../icons/AppIcon';
import { joinClassNames } from '../../utils/classNames';

/**
 * Render an accessible lightweight dropdown menu.
 *
 * @param {{
 *   label: string,
 *   items: Array<{ key: string, label: string, onSelect: () => void, disabled?: boolean }>,
 *   className?: string,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function Dropdown({ className = '', items, label }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [open]);

    const handleToggle = useCallback(() => {
        setOpen((value) => !value);
    }, []);

    const handleItemClick = useCallback(
        (event) => {
            const selectedKey = event.currentTarget.dataset.key;
            const selectedItem = items.find((item) => item.key === selectedKey);

            if (!selectedItem || selectedItem.disabled) {
                return;
            }

            selectedItem.onSelect();
            setOpen(false);
        },
        [items],
    );

    return (
        <div className={joinClassNames('cyber-dropdown', className)}>
            <button
                aria-expanded={open}
                className="cyber-dropdown__trigger"
                type="button"
                onClick={handleToggle}
            >
                <span>{label}</span>
                <AppIcon name="chevron-down" />
            </button>
            {open ? (
                <div className="cyber-dropdown__menu" role="menu">
                    {items.map((item) => (
                        <button
                            className="cyber-dropdown__item"
                            disabled={item.disabled}
                            key={item.key}
                            data-key={item.key}
                            role="menuitem"
                            type="button"
                            onClick={handleItemClick}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export default memo(Dropdown);
