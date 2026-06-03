import { memo, useCallback } from 'react';
import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render admin user pagination controls.
 *
 * @param {{
 *   pagination: Record<string, number>,
 *   paginationItems: Array<number>,
 *   t: (key: string) => string,
 *   onPageChange: (updater: number|((page: number) => number)) => void,
 * }} props
 * @returns {import('react').JSX.Element}
 */
function AdminUsersPagination({ pagination, paginationItems, t, onPageChange }) {
    const handlePrevious = useCallback(() => {
        onPageChange((page) => Math.max(1, page - 1));
    }, [onPageChange]);
    const handleNext = useCallback(() => {
        onPageChange((page) => Math.min(pagination.lastPage, page + 1));
    }, [onPageChange, pagination.lastPage]);
    const handlePageClick = useCallback(
        (event) => {
            onPageChange(Number(event.currentTarget.dataset.page));
        },
        [onPageChange],
    );

    return (
        <div className="app-user-admin-pagination">
            <button
                type="button"
                className="app-button app-button--ghost"
                disabled={pagination.currentPage <= 1}
                onClick={handlePrevious}
                title={t('adminUsers.pagination.previous')}
            >
                <AppIcon name="chevron-down" className="app-user-admin-pagination__prev-icon" />
                {t('adminUsers.pagination.previous')}
            </button>

            <div className="app-user-admin-pagination__pages">
                {paginationItems.map((page) => (
                    <button
                        key={page}
                        type="button"
                        data-page={page}
                        className={`app-user-admin-pagination__page ${
                            page === pagination.currentPage ? 'is-active' : ''
                        }`}
                        onClick={handlePageClick}
                        aria-current={page === pagination.currentPage ? 'page' : undefined}
                        aria-label={`${t('adminUsers.summary.page')} ${page}`}
                    >
                        {page}
                    </button>
                ))}
            </div>

            <button
                type="button"
                className="app-button app-button--ghost"
                disabled={pagination.currentPage >= pagination.lastPage}
                onClick={handleNext}
                title={t('adminUsers.pagination.next')}
            >
                {t('adminUsers.pagination.next')}
                <AppIcon name="chevron-down" className="app-user-admin-pagination__next-icon" />
            </button>
        </div>
    );
}

export default memo(AdminUsersPagination);
