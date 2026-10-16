import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    ActivityIcon,
    AiIcon,
    HumanIcon,
    SystemIcon,
} from '@/components/icons';
import {
    Badge,
    Button,
    Card,
    CardContent,
    EmptyState,
    Label,
    PageHeader,
    PaginationLinks,
    Select,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type AuditEntry = {
    id: number;
    action: string;
    actor_type: string;
    actor_name: string | null;
    subject_type: string | null;
    subject_id: number | null;
    properties: Record<string, unknown> | null;
    created_at: string;
};

type Props = InertiaPageProps & {
    logs: {
        data: AuditEntry[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { action: string; actor_type: string };
};

const ACTOR_META: Record<
    string,
    { variant: 'blue' | 'purple' | 'neutral'; label: string }
> = {
    human: { variant: 'blue', label: 'Human' },
    ai: { variant: 'purple', label: 'AI' },
    system: { variant: 'neutral', label: 'System' },
};

const ACTION_LABELS: Record<string, string> = {
    'document.uploaded': 'Uploaded',
    'document.validated': 'Validated',
    'document.reviewed': 'Reviewed',
    'document.auto_approved': 'Auto-approved',
    'document.status_changed': 'Status changed',
    'document.downloaded': 'Downloaded',
    'document.deleted': 'Deleted',
    'document.reprocess_requested': 'Reprocess requested',
};

const ACTOR_ICONS: Record<string, typeof HumanIcon> = {
    human: HumanIcon,
    ai: AiIcon,
    system: SystemIcon,
};

export default function ActivityIndex({ logs, filters }: Props) {
    return (
        <AppShell title="Activity" subtitle="Audit trail">
            <Head title="Activity" />

            <PageHeader
                title="Activity"
                description="Audit trail of every security-relevant action, with the actor type distinguished: humans decide, AI recommends, the system automates."
            />

            {/* Filters */}
            <Card className="mb-5">
                <form id="activity-filters" method="get">
                    <CardContent className="grid items-end gap-3 p-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="filter_actor">Actor type</Label>
                            <Select
                                id="filter_actor"
                                name="actor_type"
                                defaultValue={filters.actor_type}
                            >
                                <option value="">All</option>
                                <option value="human">Human</option>
                                <option value="ai">AI</option>
                                <option value="system">System</option>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="filter_action">Action</Label>
                            <Select
                                id="filter_action"
                                name="action"
                                defaultValue={filters.action}
                            >
                                <option value="">All</option>
                                {Object.keys(ACTION_LABELS).map((action) => (
                                    <option key={action} value={action}>
                                        {ACTION_LABELS[action]}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Button type="submit" form="activity-filters">
                                Apply filters
                            </Button>
                        </div>
                    </CardContent>
                </form>
            </Card>

            <Card>
                <CardContent className="p-5">
                    {logs.data.length === 0 ? (
                        <EmptyState
                            icon={<ActivityIcon size={20} />}
                            title="No audit entries yet"
                            description="Security-relevant actions appear here as they happen."
                        />
                    ) : (
                        <ol className="relative space-y-0">
                            {logs.data.map((log, index) => {
                                const actor =
                                    ACTOR_META[log.actor_type] ??
                                    ACTOR_META.system;
                                const ActorIcon =
                                    ACTOR_ICONS[log.actor_type] ?? SystemIcon;

                                return (
                                    <li
                                        key={log.id}
                                        className="relative flex gap-3 pb-4 last:pb-0"
                                    >
                                        {/* timeline rail */}
                                        {index < logs.data.length - 1 ? (
                                            <span
                                                aria-hidden
                                                className="absolute top-8 left-[15px] h-[calc(100%-24px)] w-px bg-border"
                                            />
                                        ) : null}
                                        <span
                                            className={`z-10 mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${
                                                actor.variant === 'blue'
                                                    ? 'bg-info-subtle text-info'
                                                    : actor.variant === 'purple'
                                                      ? 'bg-purple-subtle text-purple'
                                                      : 'bg-elevated text-muted'
                                            }`}
                                        >
                                            <ActorIcon size={14} />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant={actor.variant}>
                                                    {actor.label}
                                                </Badge>
                                                <span className="font-medium text-foreground">
                                                    {ACTION_LABELS[
                                                        log.action
                                                    ] ?? log.action}
                                                </span>
                                                {log.subject_type ===
                                                    'document' &&
                                                log.subject_id ? (
                                                    <Link
                                                        href={DocumentController.show.url(
                                                            {
                                                                document:
                                                                    log.subject_id,
                                                            },
                                                        )}
                                                        className="text-xs font-medium text-brand hover:underline"
                                                    >
                                                        document #
                                                        {log.subject_id}
                                                    </Link>
                                                ) : null}
                                            </div>
                                            <p className="mt-1 text-xs text-subtle">
                                                {log.actor_name ? (
                                                    <span className="flex items-center gap-1">
                                                        <HumanIcon size={11} />
                                                        {log.actor_name}
                                                        {' · '}
                                                    </span>
                                                ) : null}
                                                {new Date(
                                                    log.created_at,
                                                ).toLocaleString('id-ID')}
                                            </p>
                                            {log.properties &&
                                            Object.keys(log.properties).length >
                                                0 ? (
                                                <p
                                                    className="mt-1 max-w-xl truncate text-xs text-muted"
                                                    title={JSON.stringify(
                                                        log.properties,
                                                        null,
                                                        2,
                                                    )}
                                                >
                                                    {Object.entries(
                                                        log.properties,
                                                    )
                                                        .slice(0, 3)
                                                        .map(
                                                            ([key, value]) =>
                                                                `${key}: ${String(
                                                                    value,
                                                                )}`,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                            ) : null}
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    )}

                    {logs.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between gap-3">
                            <p className="text-xs text-subtle tabular-nums">
                                Page {logs.current_page} of {logs.last_page} ·{' '}
                                {logs.total} entries
                            </p>
                            <PaginationLinks links={logs.links} />
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}
