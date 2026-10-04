import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

type NavItem = {
    label: string;
    href: string;
    /**
     * Roles allowed to see the item; empty = everyone.
     */
    roles?: string[];
    phase?: string;
};

const NAV: { section: string; items: NavItem[] }[] = [
    {
        section: 'Overview',
        items: [{ label: 'Dashboard', href: '/dashboard' }],
    },
    {
        section: 'Documents',
        items: [
            {
                label: 'Documents',
                href: '/documents',
                roles: ['admin', 'verifier'],
            },
            {
                label: 'Review Queue',
                href: '/review',
                roles: ['admin', 'verifier'],
            },
            {
                label: 'Validation',
                href: '/validation',
                roles: ['admin', 'verifier'],
                phase: '5',
            },
            {
                label: 'Anomalies',
                href: '/anomalies',
                roles: ['admin', 'verifier'],
            },
        ],
    },
    {
        section: 'Intelligence',
        items: [
            { label: 'AI Assistant', href: '/ai-assistant' },
            {
                label: 'SIHANDAL',
                href: '/sihandal',
                roles: ['admin', 'verifier'],
            },
            { label: 'Reports', href: '/reports', phase: '9' },
            { label: 'Activity', href: '/activity' },
        ],
    },
    {
        section: 'Platform',
        items: [
            {
                label: 'Settings',
                href: '/settings',
                roles: ['admin', 'super_admin'],
                phase: '9',
            },
            {
                label: 'Administration',
                href: '/administration',
                roles: ['admin', 'super_admin'],
                phase: '9',
            },
        ],
    },
];

export function AppShell({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const [mobileOpen, setMobileOpen] = useState(false);

    const user = usePage().props.auth.user;
    const userRole = user.role?.slug;

    const visibleNav = NAV.map((section) => ({
        ...section,
        items: section.items.filter(
            (item) =>
                !item.roles ||
                (userRole !== undefined && item.roles.includes(userRole)),
        ),
    })).filter((section) => section.items.length > 0);

    return (
        <div className="flex min-h-screen bg-neutral-50">
            {/* Sidebar (dark, per ARCHITECTURE.md §3.2) */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-neutral-900 text-neutral-100 transition-transform lg:static lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-14 items-center gap-2 border-b border-neutral-800 px-4">
                    <div className="flex h-7 w-7 items-center justify-center rounded bg-blue-600 text-xs font-bold text-white">
                        AF
                    </div>
                    <span className="text-sm font-semibold tracking-tight">
                        Agent Finance
                    </span>
                </div>

                <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                    {visibleNav.map((section) => (
                        <div key={section.section}>
                            <p className="px-2 pb-1 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase">
                                {section.section}
                            </p>
                            <ul className="space-y-0.5">
                                {section.items.map((item) => (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm text-neutral-300 hover:bg-neutral-800 hover:text-white"
                                            onClick={() => setMobileOpen(false)}
                                        >
                                            <span>{item.label}</span>
                                            {item.phase ? (
                                                <span className="rounded bg-neutral-800 px-1.5 py-0.5 text-[10px] text-neutral-400">
                                                    P{item.phase}
                                                </span>
                                            ) : null}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>

                <div className="border-t border-neutral-800 px-4 py-3">
                    <p className="truncate text-sm font-medium text-white">
                        {user.name}
                    </p>
                    <p className="truncate text-xs text-neutral-400">
                        {[user.role?.name, user.opd?.name]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <Link
                        href={AuthController.destroy().url}
                        method="post"
                        as="button"
                        className="mt-2 block text-xs text-neutral-400 hover:text-white"
                    >
                        Sign out
                    </Link>
                </div>
            </aside>

            {mobileOpen ? (
                <div
                    className="fixed inset-0 z-20 bg-black/40 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                />
            ) : null}

            {/* Workspace */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-10 flex h-14 items-center justify-between border-b border-neutral-200 bg-white px-4 lg:px-6">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            className="rounded-md p-1.5 text-neutral-500 hover:bg-neutral-100 lg:hidden"
                            onClick={() => setMobileOpen((open) => !open)}
                            aria-label="Toggle navigation"
                        >
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                            >
                                <path
                                    d="M3 6h18M3 12h18M3 18h18"
                                    strokeLinecap="round"
                                />
                            </svg>
                        </button>
                        <h1 className="text-sm font-semibold text-neutral-900">
                            {title}
                        </h1>
                    </div>
                    <div className="hidden items-center gap-2 text-xs text-neutral-500 sm:flex">
                        {user.opd?.name ? <span>{user.opd.name}</span> : null}
                        {user.role?.name ? (
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700">
                                {user.role.name}
                            </span>
                        ) : null}
                    </div>
                </header>

                <main className="flex-1 px-4 py-6 lg:px-6">{children}</main>
            </div>
        </div>
    );
}
