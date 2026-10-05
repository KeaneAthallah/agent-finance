import { useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { useTheme } from '@/lib/theme';
import AdminController from '@/actions/App/Http/Controllers/AdminController';
import AiAssistantController from '@/actions/App/Http/Controllers/AiAssistantController';
import AnomalyController from '@/actions/App/Http/Controllers/AnomalyController';
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import ReportsController from '@/actions/App/Http/Controllers/ReportsController';
import ReviewController from '@/actions/App/Http/Controllers/ReviewController';
import SectionController from '@/actions/App/Http/Controllers/SectionController';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import SihandalController from '@/actions/App/Http/Controllers/SihandalController';
import ActivityController from '@/actions/App/Http/Controllers/ActivityController';
import {
    ActivityIcon,
    AiIcon,
    AnomaliesIcon,
    BellIcon,
    BuildingIcon,
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    DashboardIcon,
    DocumentsIcon,
    LogoutIcon,
    MenuIcon,
    MonitorIcon,
    MoonIcon,
    ReviewIcon,
    ReportsIcon,
    SearchIcon,
    SettingsIcon,
    SihandalIcon,
    SunIcon,
    UsersIcon,
    ValidationIcon,
    XIcon,
} from '@/components/icons';
import { Dropdown, DropdownItem, ThemeSwitcher } from '@/components/ui';

type NavItem = {
    label: string;
    href: string;
    icon: typeof DashboardIcon;
    /** Roles allowed to see the item; empty = everyone. */
    roles?: string[];
    phase?: string;
};

const NAV: { section: string; items: NavItem[] }[] = [
    {
        section: 'Overview',
        items: [
            {
                label: 'Dashboard',
                href: DashboardController.url(),
                icon: DashboardIcon,
            },
        ],
    },
    {
        section: 'Intelligence',
        items: [
            {
                label: 'Documents',
                href: DocumentController.index.url(),
                icon: DocumentsIcon,
                roles: ['admin', 'verifier'],
            },
            {
                label: 'AI Assistant',
                href: AiAssistantController.index.url(),
                icon: AiIcon,
            },
            {
                label: 'Review Queue',
                href: ReviewController.index.url(),
                icon: ReviewIcon,
                roles: ['admin', 'verifier'],
            },
            {
                label: 'Validation',
                href: SectionController.validation.url(),
                icon: ValidationIcon,
                roles: ['admin', 'verifier'],
                phase: '5',
            },
            {
                label: 'Anomalies',
                href: AnomalyController.index.url(),
                icon: AnomaliesIcon,
                roles: ['admin', 'verifier'],
            },
            {
                label: 'Reports',
                href: ReportsController.index.url(),
                icon: ReportsIcon,
            },
        ],
    },
    {
        section: 'Integration',
        items: [
            {
                label: 'SIHANDAL',
                href: SihandalController.index.url(),
                icon: SihandalIcon,
                roles: ['admin', 'verifier'],
            },
            {
                label: 'Activity',
                href: ActivityController.index.url(),
                icon: ActivityIcon,
            },
        ],
    },
    {
        section: 'Platform',
        items: [
            {
                label: 'Settings',
                href: SettingsController.index.url(),
                icon: SettingsIcon,
                roles: ['admin', 'super_admin'],
            },
            {
                label: 'Administration',
                href: AdminController.index.url(),
                icon: UsersIcon,
                roles: ['admin', 'super_admin'],
            },
        ],
    },
];

const SIDEBAR_KEY = 'agent-finance.sidebar';

function storedCollapsed(): boolean {
    try {
        return (
            typeof window !== 'undefined' &&
            window.localStorage?.getItem(SIDEBAR_KEY) === 'collapsed'
        );
    } catch {
        return false;
    }
}

export function AppShell({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle?: string;
    children: ReactNode;
}) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(storedCollapsed);
    const [search, setSearch] = useState('');

    const { preference, setPreference } = useTheme();

    const user = usePage().props.auth.user;
    const userRole = user.role?.slug;
    const currentUrl = (usePage().url ?? '') as string;

    const visibleNav = useMemo(
        () =>
            NAV.map((section) => ({
                ...section,
                items: section.items.filter(
                    (item) =>
                        !item.roles ||
                        (userRole !== undefined &&
                            item.roles.includes(userRole)),
                ),
            })).filter((section) => section.items.length > 0),
        [userRole],
    );

    const activeSection = useMemo(() => {
        for (const section of visibleNav) {
            for (const item of section.items) {
                if (
                    currentUrl === item.href ||
                    currentUrl.startsWith(`${item.href}/`)
                ) {
                    return section.section;
                }
            }
        }

        return null;
    }, [currentUrl, visibleNav]);

    function toggleCollapsed() {
        setCollapsed((value) => {
            const next = !value;

            try {
                window.localStorage?.setItem(
                    SIDEBAR_KEY,
                    next ? 'collapsed' : 'expanded',
                );
            } catch {
                /* storage unavailable — collapse state is session-only */
            }

            return next;
        });
    }

    function submitSearch(event: React.FormEvent) {
        event.preventDefault();

        const trimmed = search.trim();

        router.get(
            DocumentController.index.url(
                trimmed ? { query: { search: trimmed } } : undefined,
            ),
        );
    }

    const initials = user.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');

    const sidebar = (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-border bg-surface transition-[width,transform] duration-200 ease-out lg:static lg:translate-x-0',
                collapsed ? 'w-16' : 'w-64',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
            )}
        >
            {/* Brand */}
            <div className="flex h-14 shrink-0 items-center gap-2.5 border-b border-border px-3">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand text-xs font-bold text-brand-foreground">
                    AF
                </span>
                <span
                    className={cn(
                        'truncate text-sm font-semibold tracking-tight text-foreground transition-opacity',
                        collapsed && 'sr-only',
                    )}
                >
                    Agent Finance
                </span>
            </div>

            {/* Navigation */}
            <nav
                aria-label="Main"
                className="scroll-thin flex-1 space-y-5 overflow-y-auto px-2 py-4"
            >
                {visibleNav.map((section) => (
                    <div key={section.section}>
                        <p
                            className={cn(
                                'px-2 pb-1 text-[11px] font-semibold tracking-wider text-subtle uppercase',
                                collapsed && 'sr-only',
                            )}
                        >
                            {section.section}
                        </p>
                        <ul className="space-y-0.5">
                            {section.items.map((item) => {
                                const active =
                                    currentUrl === item.href ||
                                    currentUrl.startsWith(`${item.href}/`);
                                const Icon = item.icon;

                                return (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            onClick={() => setMobileOpen(false)}
                                            title={item.label}
                                            className={cn(
                                                'flex items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors',
                                                active
                                                    ? 'bg-brand-subtle font-medium text-brand'
                                                    : 'text-muted hover:bg-elevated hover:text-foreground',
                                                collapsed &&
                                                    'justify-center px-0',
                                            )}
                                        >
                                            <Icon
                                                size={17}
                                                className="shrink-0"
                                            />
                                            <span
                                                className={cn(
                                                    'min-w-0 flex-1 truncate',
                                                    collapsed && 'sr-only',
                                                )}
                                            >
                                                {item.label}
                                            </span>
                                            {item.phase && !collapsed ? (
                                                <span className="rounded bg-elevated px-1.5 py-0.5 text-[10px] font-medium text-subtle">
                                                    P{item.phase}
                                                </span>
                                            ) : null}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            {/* Collapse toggle (desktop) */}
            <div className="hidden shrink-0 border-t border-border p-2 lg:block">
                <button
                    type="button"
                    onClick={toggleCollapsed}
                    aria-label={
                        collapsed ? 'Expand sidebar' : 'Collapse sidebar'
                    }
                    title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    className={cn(
                        'flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-sm text-muted transition-colors hover:bg-elevated hover:text-foreground',
                        collapsed && 'justify-center px-0',
                    )}
                >
                    {collapsed ? (
                        <ChevronRightIcon size={17} />
                    ) : (
                        <ChevronLeftIcon size={17} />
                    )}
                    <span className={cn('truncate', collapsed && 'sr-only')}>
                        Collapse
                    </span>
                </button>
            </div>

            {/* User identity (kept in the DOM for screen readers
                even when the sidebar is collapsed) */}
            <div className="shrink-0 border-t border-border px-3 py-3">
                <div
                    className={cn(
                        'flex items-center gap-2.5',
                        collapsed && 'justify-center',
                    )}
                >
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-brand">
                        {initials}
                    </span>
                    <div
                        className={cn('min-w-0 flex-1', collapsed && 'sr-only')}
                    >
                        <p className="truncate text-sm font-medium text-foreground">
                            {user.name}
                        </p>
                        <p className="truncate text-xs text-subtle">
                            {[user.role?.name, user.opd?.name]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    );

    return (
        <div className="flex min-h-screen bg-background">
            {sidebar}

            {mobileOpen ? (
                <div
                    className="fixed inset-0 z-30 bg-foreground/40 lg:hidden"
                    onClick={() => setMobileOpen(false)}
                    aria-hidden
                />
            ) : null}

            {/* Workspace */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-surface/90 px-3 backdrop-blur lg:px-5">
                    <button
                        type="button"
                        className="cursor-pointer rounded-md p-1.5 text-muted hover:bg-elevated lg:hidden"
                        onClick={() => setMobileOpen((open) => !open)}
                        aria-label="Toggle navigation"
                        aria-expanded={mobileOpen}
                    >
                        {mobileOpen ? (
                            <XIcon size={20} />
                        ) : (
                            <MenuIcon size={20} />
                        )}
                    </button>

                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            {activeSection ? (
                                <span className="hidden items-center gap-1.5 text-xs text-subtle sm:flex">
                                    <span className="max-w-40 truncate">
                                        {activeSection}
                                    </span>
                                    <ChevronRightIcon size={12} />
                                </span>
                            ) : null}
                            <h1 className="truncate text-sm font-semibold text-foreground">
                                {title}
                            </h1>
                        </div>
                        {subtitle ? (
                            <p className="hidden truncate text-xs text-subtle md:block">
                                {subtitle}
                            </p>
                        ) : null}
                    </div>

                    {/* Global search — routes to the document workspace */}
                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="relative hidden md:block"
                    >
                        <SearchIcon
                            size={15}
                            className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-subtle"
                        />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search documents…"
                            aria-label="Search documents"
                            className="h-9 w-48 rounded-md border border-border bg-elevated pr-3 pl-8 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none lg:w-64"
                        />
                    </form>

                    <div className="flex shrink-0 items-center gap-1">
                        {/* Notifications */}
                        <Dropdown
                            label="Notifications"
                            trigger={
                                <span className="flex h-9 w-9 items-center justify-center rounded-md text-muted transition-colors hover:bg-elevated hover:text-foreground">
                                    <BellGlyph />
                                </span>
                            }
                        >
                            <p className="px-2.5 py-1.5 text-xs font-semibold text-foreground">
                                Notifications
                            </p>
                            <div className="px-2.5 py-3 text-center">
                                <p className="text-sm text-muted">
                                    You&apos;re all caught up
                                </p>
                                <p className="mt-0.5 text-xs text-subtle">
                                    Review requests and sync results appear
                                    here.
                                </p>
                            </div>
                        </Dropdown>

                        {/* Theme */}
                        <span className="hidden sm:inline-flex">
                            <ThemeSwitcher />
                        </span>

                        {/* User menu */}
                        <Dropdown
                            label="Account"
                            trigger={
                                <span className="flex h-9 items-center gap-2 rounded-md px-1.5 text-muted transition-colors hover:bg-elevated hover:text-foreground">
                                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-subtle text-[11px] font-semibold text-brand">
                                        {initials}
                                    </span>
                                </span>
                            }
                        >
                            <div className="border-b border-border px-2.5 py-2">
                                <p className="truncate text-sm font-medium text-foreground">
                                    {user.name}
                                </p>
                                <p className="truncate text-xs text-subtle">
                                    {user.email}
                                </p>
                                <p className="mt-1 flex flex-wrap items-center gap-1 text-xs text-muted">
                                    {user.role?.name ? (
                                        <span className="rounded bg-elevated px-1.5 py-0.5 font-medium">
                                            {user.role.name}
                                        </span>
                                    ) : null}
                                    {user.opd ? (
                                        <span className="inline-flex items-center gap-1">
                                            <BuildingIcon size={11} />
                                            {user.opd.name}
                                        </span>
                                    ) : null}
                                </p>
                            </div>

                            <p className="px-2.5 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-subtle uppercase">
                                Theme
                            </p>
                            {(
                                [
                                    {
                                        value: 'light',
                                        label: 'Light',
                                        icon: SunIcon,
                                    },
                                    {
                                        value: 'dark',
                                        label: 'Dark',
                                        icon: MoonIcon,
                                    },
                                    {
                                        value: 'system',
                                        label: 'System',
                                        icon: MonitorIcon,
                                    },
                                ] as const
                            ).map((option) => (
                                <DropdownItem
                                    key={option.value}
                                    icon={<option.icon size={15} />}
                                    onClick={() => setPreference(option.value)}
                                >
                                    <span className="flex flex-1 items-center justify-between">
                                        {option.label}
                                        {preference === option.value ? (
                                            <CheckIcon size={14} />
                                        ) : null}
                                    </span>
                                </DropdownItem>
                            ))}

                            <div className="my-1.5 border-t border-border" />

                            <Link
                                href={AuthController.destroy().url}
                                method="post"
                                as="button"
                                onClick={() => setMobileOpen(false)}
                                className="flex w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm text-danger transition-colors hover:bg-danger-subtle"
                            >
                                <LogoutIcon size={15} />
                                Sign out
                            </Link>
                        </Dropdown>
                    </div>
                </header>

                <main className="flex-1 px-3 py-5 lg:px-6">{children}</main>
            </div>
        </div>
    );
}

function BellGlyph() {
    return <BellIcon size={18} />;
}
