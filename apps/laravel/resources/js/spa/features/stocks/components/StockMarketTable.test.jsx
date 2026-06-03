import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StockMarketTable from './StockMarketTable';

const stocks = [
    {
        symbol: 'AAPL',
        name: 'Apple Inc.',
        exchange: 'NASDAQ',
        is_favorite: true,
    },
    {
        symbol: 'MSFT',
        name: 'Microsoft Corporation',
        exchange: 'NASDAQ',
        is_favorite: false,
    },
];

const labels = {
    'stocksPage.list.title': 'Stock Watch',
    'stocksPage.list.text': 'Search and favorite tracked stocks.',
    'stocksPage.searchPlaceholder': 'Search stocks...',
    'stocksPage.table.symbol': 'Symbol',
    'stocksPage.table.company': 'Company',
    'stocksPage.table.exchange': 'Exchange',
    'stocksPage.table.favorite': 'Favorite',
    'stocksPage.actions.toggleFavorite': 'Toggle favorite',
};

const translate = (key) => labels[key] ?? key;

/**
 * Render stock table with configurable handlers.
 *
 * @param {{ onFavoriteToggle?: (symbol: string) => void, onQueryChange?: (query: string) => void }} options
 * @returns {import('@testing-library/react').RenderResult}
 */
function renderTable({ onFavoriteToggle = vi.fn(), onQueryChange = vi.fn() } = {}) {
    return render(
        <StockMarketTable
            query=""
            stocks={stocks}
            t={translate}
            onFavoriteToggle={onFavoriteToggle}
            onQueryChange={onQueryChange}
        />,
    );
}

describe('StockMarketTable', () => {
    afterEach(() => {
        cleanup();
    });

    it('renders the searchable stock rows', () => {
        renderTable();

        expect(screen.getByRole('heading', { name: 'Stock Watch' })).toBeInTheDocument();
        expect(screen.getByLabelText('Search stocks...')).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Symbol' })).toBeInTheDocument();
        expect(screen.getByText('AAPL')).toBeInTheDocument();
        expect(screen.getByText('Apple Inc.')).toBeInTheDocument();
        expect(screen.getByText('MSFT')).toBeInTheDocument();
        expect(screen.getByText('Microsoft Corporation')).toBeInTheDocument();
    });

    it('passes search and favorite actions to the owning page', () => {
        const onFavoriteToggle = vi.fn();
        const onQueryChange = vi.fn();
        renderTable({ onFavoriteToggle, onQueryChange });

        fireEvent.change(screen.getByLabelText('Search stocks...'), {
            target: { value: 'msft' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Toggle favorite MSFT' }));

        expect(onQueryChange).toHaveBeenCalledWith('msft');
        expect(onFavoriteToggle).toHaveBeenCalledWith('MSFT');
    });
});
