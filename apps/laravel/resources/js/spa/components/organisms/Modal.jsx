import { memo, useCallback, useEffect } from 'react';
import { createPortal } from 'react-dom';
import AppIcon from '../icons/AppIcon';
import { joinClassNames } from '../../utils/classNames';

/**
 * Render a reusable modal dialog.
 *
 * @param {{
 *   open: boolean,
 *   title: string,
 *   children: import('react').ReactNode,
 *   className?: string,
 *   onClose: () => void,
 * }} props
 * @returns {import('react').ReactPortal | import('react').JSX.Element | null}
 */
function Modal({ children, className = '', onClose, open, title }) {
    useEffect(() => {
        if (!open || typeof document === 'undefined') {
            return undefined;
        }

        const { body, documentElement } = document;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlOverflow = documentElement.style.overflow;

        const handleKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        body.style.overflow = 'hidden';
        documentElement.style.overflow = 'hidden';
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [onClose, open]);

    const handleDialogMouseDown = useCallback((event) => {
        event.stopPropagation();
    }, []);

    if (!open) {
        return null;
    }

    const content = (
        <div className="cyber-modal-backdrop" role="presentation" onMouseDown={onClose}>
            <section
                aria-labelledby="cyber-modal-title"
                aria-modal="true"
                className={joinClassNames('cyber-modal', className)}
                role="dialog"
                onMouseDown={handleDialogMouseDown}
            >
                <header className="cyber-modal__header">
                    <h2 id="cyber-modal-title">{title}</h2>
                    <button aria-label="Close modal" type="button" onClick={onClose}>
                        <AppIcon name="x" />
                    </button>
                </header>
                <div className="cyber-modal__body">{children}</div>
            </section>
        </div>
    );

    return typeof document === 'undefined' ? content : createPortal(content, document.body);
}

export default memo(Modal);
