const flowNodes = [
    { x: 58, y: 62, label: 'API', tone: 'lime' },
    { x: 178, y: 32, label: 'n8n', tone: 'pink' },
    { x: 178, y: 92, label: 'DB', tone: 'cyan' },
    { x: 300, y: 62, label: 'OPAS', tone: 'orange' },
    { x: 424, y: 32, label: 'AI', tone: 'lime' },
    { x: 424, y: 92, label: 'Desk', tone: 'cyan' },
];

const flowEdges = [
    [0, 1],
    [0, 2],
    [1, 3],
    [2, 3],
    [3, 4],
    [3, 5],
];

/**
 * Render the auth flow graph adapted from the source login template.
 *
 * @param {{ className?: string, label?: string }} props
 * @returns {import('react').JSX.Element}
 */
export default function LoginFlowDiagram({ className = '', label = 'OPAS login flow' }) {
    return (
        <svg
            viewBox="0 0 482 124"
            className={`app-auth-login-card__flow ${className}`.trim()}
            role="img"
            aria-label={label}
        >
            {flowEdges.map(([sourceIndex, targetIndex]) => {
                const source = flowNodes[sourceIndex];
                const target = flowNodes[targetIndex];
                const midX = (source.x + target.x) / 2;

                return (
                    <path
                        key={`${source.label}-${target.label}`}
                        d={`M ${source.x + 22} ${source.y} C ${midX} ${source.y}, ${midX} ${target.y}, ${target.x - 22} ${target.y}`}
                        className={`app-auth-login-card__flow-line app-auth-login-card__flow-line--${target.tone}`}
                    />
                );
            })}
            {flowNodes.map((node) => (
                <g
                    key={node.label}
                    className={`app-auth-login-card__flow-node app-auth-login-card__flow-node--${node.tone}`}
                >
                    <rect x={node.x - 24} y={node.y - 12} width="48" height="24" rx="4" />
                    <text x={node.x} y={node.y + 3} textAnchor="middle">
                        {node.label}
                    </text>
                </g>
            ))}
        </svg>
    );
}
