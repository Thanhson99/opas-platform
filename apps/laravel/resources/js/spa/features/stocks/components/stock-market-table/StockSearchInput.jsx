/**
 * Render the stock search control.
 *
 * @param {{ query: string, placeholder: string, onQueryChange: (query: string) => void }} props
 * @returns {import('react').JSX.Element}
 */
export default function StockSearchInput({ query, placeholder, onQueryChange }) {
    return (
        <div className="app-search-row">
            <label className="app-visually-hidden" htmlFor="stocks-search">
                {placeholder}
            </label>
            <input
                id="stocks-search"
                type="search"
                className="app-input"
                placeholder={placeholder}
                value={query}
                aria-label={placeholder}
                onChange={(event) => onQueryChange(event.target.value)}
            />
        </div>
    );
}
