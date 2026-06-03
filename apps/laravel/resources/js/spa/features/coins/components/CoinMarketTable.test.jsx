import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CoinMarketTable from './CoinMarketTable';

const coins = [
    {
        symbol: 'BTCUSDT',
        lastPrice: '68450.25',
        quoteVolume: '1200000000',
        priceChangePercent: '4.25',
        is_favorite: true,
    },
    {
        symbol: 'ETHUSDT',
        lastPrice: '3150',
        quoteVolume: '820000000',
        priceChangePercent: '-1.5',
        is_favorite: false,
    },
];

const labels = {
    'coinsPage.list.title': 'Market Watch',
    'coinsPage.list.text': 'Live coin market data.',
    'coinsPage.list.symbolHint': 'Symbols update live',
    'coinsPage.list.favoriteHint': 'Favorites sync to account',
    'coinsPage.table.symbol': 'Symbol',
    'coinsPage.table.price': 'Price',
    'coinsPage.table.volume': 'Volume',
    'coinsPage.table.change': '24h',
    'coinsPage.table.favorite': 'Favorite',
    'coinsPage.actions.toggleFavorite': 'Toggle favorite',
};

const translate = (key) => labels[key] ?? key;

/**
 * Render the coin market table inside router context.
 *
 * @param {{ onFavoriteToggle?: (symbol: string) => void }} options
 * @returns {import('@testing-library/react').RenderResult}
 */
function renderTable({ onFavoriteToggle = vi.fn() } = {}) {
    return render(
        <MemoryRouter>
            <CoinMarketTable coins={coins} t={translate} onFavoriteToggle={onFavoriteToggle} />
        </MemoryRouter>,
    );
}

describe('CoinMarketTable', () => {
    afterEach(() => {
        cleanup();
    });

    it('renders market rows with detail links and formatted values', () => {
        renderTable();

        expect(screen.getByRole('heading', { name: 'Market Watch' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'BTCUSDT' })).toHaveAttribute(
            'href',
            '/coins/show/BTCUSDT',
        );
        expect(screen.getByRole('link', { name: 'ETHUSDT' })).toHaveAttribute(
            'href',
            '/coins/show/ETHUSDT',
        );
        expect(screen.getByText('$68,450.25')).toBeInTheDocument();
        expect(screen.getByText('$1.2B')).toBeInTheDocument();
        expect(screen.getByText('4.25%')).toBeInTheDocument();
        expect(screen.getByText('-1.50%')).toBeInTheDocument();
    });

    it('calls the favorite handler with the selected symbol', () => {
        const onFavoriteToggle = vi.fn();
        renderTable({ onFavoriteToggle });

        fireEvent.click(screen.getByRole('button', { name: 'Toggle favorite ETHUSDT' }));

        expect(onFavoriteToggle).toHaveBeenCalledWith('ETHUSDT');
    });
});
