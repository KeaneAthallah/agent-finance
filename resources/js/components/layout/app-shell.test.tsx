import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

// Role is mutable so each test renders the shell as a
// different user; vi.mock factories are hoisted, so the
// state travels through a hoisted holder.
const state = vi.hoisted(() => ({ role: undefined as string | undefined }));

vi.mock('@inertiajs/react', () => ({
    Link: (props: { children?: React.ReactNode; href?: string }) => (
        <a href={props.href}>{props.children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    name: 'Test User',
                    role: state.role
                        ? { slug: state.role, name: state.role }
                        : null,
                    opd: { name: 'Dinas Pendidikan' },
                },
            },
        },
    }),
}));

vi.mock(
    '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController',
    () => ({
        default: {
            destroy: () => ({ url: '/logout', method: 'post' }),
        },
    }),
);

import { AppShell } from '@/components/layout/app-shell';

function renderShell() {
    return render(
        <AppShell title="Dashboard">
            <p>Page body</p>
        </AppShell>,
    );
}

describe('AppShell navigation (§36)', () => {
    it('shows the shared nav to every user', () => {
        state.role = 'viewer';
        renderShell();

        expect(
            screen.getByRole('link', { name: 'Dashboard' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'AI Assistant' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Reports' }),
        ).toBeInTheDocument();
    });

    it('hides role-gated sections from viewers', () => {
        state.role = 'viewer';
        renderShell();

        expect(
            screen.queryByRole('link', { name: 'Documents' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Administration' }),
        ).not.toBeInTheDocument();
    });

    it('shows document sections to verifiers', () => {
        state.role = 'verifier';
        renderShell();

        expect(
            screen.getByRole('link', { name: 'Documents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Review Queue' }),
        ).toBeInTheDocument();
    });

    it('shows the platform surface to super admins', () => {
        state.role = 'super_admin';
        renderShell();

        expect(
            screen.getByRole('link', { name: 'Settings' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Administration' }),
        ).toBeInTheDocument();
    });

    it('renders the user identity in the sidebar', () => {
        state.role = 'viewer';
        renderShell();

        expect(screen.getByText('Test User')).toBeInTheDocument();
        expect(screen.getAllByText(/Dinas Pendidikan/).length).toBeGreaterThan(
            0,
        );
    });
});
