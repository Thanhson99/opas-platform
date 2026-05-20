import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * Render the shared confirmation modal used for destructive or important actions.
 *
 * @param {{
 *   open: boolean,
 *   eyebrow?: string,
 *   title: string,
 *   text: string,
 *   confirmLabel: string,
 *   cancelLabel: string,
 *   tone?: 'primary' | 'danger',
 *   onConfirm: () => void,
 *   onCancel: () => void,
 * }} props
 * @returns {import('react').ReactPortal | import('react').JSX.Element | null}
 */
export default function ConfirmModal({
    open,
    eyebrow,
    title,
    text,
    confirmLabel,
    cancelLabel,
    tone = 'primary',
    onConfirm,
    onCancel,
}) {
    const [modalViewportPosition, setModalViewportPosition] = useState({
        top: 0,
        left: 0,
        backdropHeight: 0,
    });

    useEffect(() => {
        if (!open || typeof document === 'undefined') {
            return undefined;
        }

        const { body, documentElement } = document;
        const previousBodyOverflow = body.style.overflow;
        const previousHtmlOverflow = documentElement.style.overflow;

        const updateModalViewportPosition = () => {
            const scrollTop = window.scrollY || documentElement.scrollTop || body.scrollTop || 0;
            const scrollLeft = window.scrollX || documentElement.scrollLeft || body.scrollLeft || 0;
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            const documentHeight = Math.max(
                body.scrollHeight,
                body.offsetHeight,
                documentElement.clientHeight,
                documentElement.scrollHeight,
                documentElement.offsetHeight,
            );

            // Keep the modal centered inside the area the user is currently viewing,
            // even when the page is already scrolled deep into a long form.
            setModalViewportPosition({
                top: scrollTop + viewportHeight / 2,
                left: scrollLeft + viewportWidth / 2,
                backdropHeight: Math.max(documentHeight, scrollTop + viewportHeight),
            });
        };

        updateModalViewportPosition();
        body.style.overflow = 'hidden';
        documentElement.style.overflow = 'hidden';
        window.addEventListener('resize', updateModalViewportPosition);

        return () => {
            body.style.overflow = previousBodyOverflow;
            documentElement.style.overflow = previousHtmlOverflow;
            window.removeEventListener('resize', updateModalViewportPosition);
        };
    }, [open]);

    if (!open) {
        return null;
    }

    const content = (
        <div
            className="app-modal-backdrop"
            role="presentation"
            style={{ minHeight: `${modalViewportPosition.backdropHeight}px` }}
            onClick={onCancel}
        >
            <div
                className="app-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-modal-title"
                style={{
                    top: `${modalViewportPosition.top}px`,
                    left: `${modalViewportPosition.left}px`,
                }}
                onClick={(event) => event.stopPropagation()}
            >
                <div className="app-modal__body">
                    {eyebrow ? <p className="app-modal__eyebrow">{eyebrow}</p> : null}
                    <h3 className="app-modal__title" id="confirm-modal-title">
                        {title}
                    </h3>
                    <p className="app-modal__text">{text}</p>
                </div>

                <div className="app-modal__actions">
                    <button
                        type="button"
                        className="app-button app-button--ghost"
                        onClick={onCancel}
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        className={`app-button ${
                            tone === 'danger' ? 'app-button--danger' : 'app-button--primary'
                        }`}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );

    if (typeof document === 'undefined') {
        return content;
    }

    return createPortal(content, document.body);
}
