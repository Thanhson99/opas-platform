import AppIcon from '../../../../components/icons/AppIcon';

/**
 * Render the favorite toggle for one market row.
 *
 * @param {{ symbol: string, isFavorite: boolean, label: string, onToggle: (symbol: string) => void }} props
 * @returns {import('react').JSX.Element}
 */
export default function CoinFavoriteButton({ symbol, isFavorite, label, onToggle }) {
    return (
        <button
            type="button"
            className={`app-favorite ${isFavorite ? 'is-active' : ''}`}
            onClick={() => onToggle(symbol)}
            aria-label={`${label} ${symbol}`}
        >
            <AppIcon
                name="heart"
                filled={isFavorite}
                className={isFavorite ? 'is-favorite' : 'is-favorite-muted'}
            />
        </button>
    );
}
