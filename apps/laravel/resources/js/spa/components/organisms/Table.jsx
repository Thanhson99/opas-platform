import LoadingSkeleton from '../atoms/LoadingSkeleton';

/**
 * Render a reusable data table with loading, empty, and error states.
 *
 * @param {{
 *   columns: Array<{ key: string, label: string, render?: (row: Record<string, unknown>) => import('react').ReactNode }>,
 *   rows: Array<Record<string, unknown>>,
 *   getRowKey: (row: Record<string, unknown>) => string,
 *   emptyText?: string,
 *   errorText?: string,
 *   loading?: boolean,
 * }} props
 * @returns {import('react').JSX.Element}
 */
export default function Table({
    columns,
    emptyText = 'No data available.',
    errorText = '',
    getRowKey,
    loading = false,
    rows,
}) {
    if (loading) {
        return <LoadingSkeleton rows={5} variant="table" />;
    }

    if (errorText) {
        return <p className="cyber-table__feedback">{errorText}</p>;
    }

    if (rows.length === 0) {
        return <p className="cyber-table__feedback">{emptyText}</p>;
    }

    return (
        <div className="cyber-table-wrap">
            <table className="cyber-table">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key}>{column.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={getRowKey(row)}>
                            {columns.map((column) => (
                                <td key={column.key}>
                                    {column.render ? column.render(row) : row[column.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
