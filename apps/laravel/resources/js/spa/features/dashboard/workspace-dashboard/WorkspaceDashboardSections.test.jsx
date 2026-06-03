import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import {
    WorkspaceDashboardContent,
    WorkspaceDashboardHero,
    WorkspaceDashboardStats,
} from './WorkspaceDashboardSections';

const stats = [
    {
        label: 'Total profit',
        note: '(30 days)',
        value: '$128,450.75',
        delta: '12.5%',
        deltaText: 'vs previous period',
        icon: 'dollar',
        tone: 'blue',
    },
];

const highlights = [
    {
        title: 'Workflow automation',
        text: 'Coordinate market workflows.',
        icon: 'workflow',
        tone: 'blue',
    },
];

const modules = [
    {
        title: 'Coins',
        text: 'Track crypto markets.',
        href: '/coins',
        icon: 'coins',
        tone: 'blue',
    },
    {
        title: 'Stocks',
        text: 'Track equities.',
        href: '/stocks',
        icon: 'stocks',
        tone: 'green',
    },
];

const activity = [
    {
        title: 'Created alert',
        emphasis: '"BTC Alert"',
        timestamp: '23/05/2024 14:32',
        icon: 'check',
        tone: 'green',
    },
];

/**
 * Render dashboard content sections with router support.
 *
 * @param {import('react').ReactNode} children
 * @returns {import('@testing-library/react').RenderResult}
 */
function renderWithRouter(children) {
    return render(<MemoryRouter>{children}</MemoryRouter>);
}

describe('WorkspaceDashboardSections', () => {
    afterEach(() => {
        cleanup();
    });

    it('renders the dashboard stat values and deltas', () => {
        render(<WorkspaceDashboardStats items={stats} />);

        expect(screen.getByText('Total profit')).toBeInTheDocument();
        expect(screen.getByText('(30 days)')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: '$128,450.75' })).toBeInTheDocument();
        expect(screen.getByText('12.5%')).toBeInTheDocument();
        expect(screen.getByText('vs previous period')).toBeInTheDocument();
    });

    it('renders hero actions and highlight cards', () => {
        render(
            <WorkspaceDashboardHero
                eyebrow="OPAS Control"
                title="Automation command center"
                text="Manage the workspace from one surface."
                primaryLabel="Start now"
                secondaryLabel="View modules"
                highlights={highlights}
            />,
        );

        expect(screen.getByText('OPAS Control')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Automation command center' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Start now' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'View modules' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Workflow automation' })).toBeInTheDocument();
        expect(screen.getByText('Coordinate market workflows.')).toBeInTheDocument();
    });

    it('renders module links and recent activity', () => {
        renderWithRouter(
            <WorkspaceDashboardContent
                modulesTitle="Workspace modules"
                openLabel="Open"
                viewAllLabel="View all"
                activityTitle="Recent activity"
                modules={modules}
                activity={activity}
            />,
        );

        expect(screen.getByRole('heading', { name: 'Workspace modules' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Open Coins' })).toHaveAttribute('href', '/coins');
        expect(screen.getByRole('link', { name: 'Open Stocks' })).toHaveAttribute(
            'href',
            '/stocks',
        );
        expect(screen.getByRole('heading', { name: 'Recent activity' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'View all' })).toBeInTheDocument();
        expect(screen.getByText(/Created alert/)).toBeInTheDocument();
        expect(screen.getByText('"BTC Alert"')).toBeInTheDocument();
    });
});
