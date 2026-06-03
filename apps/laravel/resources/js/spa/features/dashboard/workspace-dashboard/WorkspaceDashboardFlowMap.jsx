const flowNodes = [
    { x: 58, y: 82, label: 'Coins', tone: 'lime' },
    { x: 188, y: 42, label: 'n8n', tone: 'pink' },
    { x: 190, y: 142, label: 'Stocks', tone: 'cyan' },
    { x: 326, y: 92, label: 'OPAS', tone: 'purple' },
    { x: 470, y: 52, label: 'Content', tone: 'lime' },
    { x: 472, y: 152, label: 'Video', tone: 'warning' },
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
 * Render the cyber workflow map adapted from the template source.
 *
 * @returns {import('react').JSX.Element}
 */
export default function WorkspaceDashboardFlowMap() {
    return (
        <svg
            viewBox="0 0 540 204"
            className="workspace-dashboard__flow-map"
            role="img"
            aria-label="OPAS automation flow"
        >
            {flowEdges.map(([sourceIndex, targetIndex]) => {
                const source = flowNodes[sourceIndex];
                const target = flowNodes[targetIndex];
                const midX = (source.x + target.x) / 2;

                return (
                    <path
                        key={`${source.label}-${target.label}`}
                        d={`M ${source.x + 25} ${source.y} C ${midX} ${source.y}, ${midX} ${target.y}, ${target.x - 25} ${target.y}`}
                        className={`workspace-dashboard__flow-line workspace-dashboard__flow-line--${target.tone}`}
                    />
                );
            })}
            {flowNodes.map((node) => (
                <g
                    key={node.label}
                    className={`workspace-dashboard__flow-node workspace-dashboard__flow-node--${node.tone}`}
                >
                    <rect x={node.x - 30} y={node.y - 14} width="60" height="28" rx="5" />
                    <text x={node.x} y={node.y + 4} textAnchor="middle">
                        {node.label}
                    </text>
                </g>
            ))}
        </svg>
    );
}
