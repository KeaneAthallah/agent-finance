import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import { Badge, Card, CardContent } from '@/components/ui';
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

const ACTOR_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red' | 'purple'
> = {
    human: 'blue',
    ai: 'purple',
    system: 'neutral',
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

export default function ActivityIndex({ logs, filters }: Props) {
    return (
        <AppShell title="Activity">
            <Head title="Activity" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Audit trail of every security-relevant action, with the actor
                type distinguished: humans decide, AI recommends, the system
                automates (spec §35).
            </p>

            <Card>
                <CardContent className="p-4">
                    <form
                        className="mb-4 flex flex-wrap items-end gap-3"
                        method="get"
                    >
                        <div>
                            <label
                                htmlFor="filter_actor"
                                className="mb-1 block text-xs font-medium text-neutral-600"
                            >
                                Actor type
                            </label>
                            <select
                                id="filter_actor"
                                name="actor_type"
                                defaultValue={filters.actor_type}
                                className="h-10 rounded-md border border-neutral-300 bg-white px-2 text-sm"
                            >
                                <option value="">All</option>
                                <option value="human">Human</option>
                                <option value="ai">AI</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <div>
                            <label
                                htmlFor="filter_action"
                                className="mb-1 block text-xs font-medium text-neutral-600"
                            >
                                Action
                            </label>
                            <select
                                id="filter_action"
                                name="action"
                                defaultValue={filters.action}
                                className="h-10 rounded-md border border-neutral-300 bg-white px-2 text-sm"
                            >
                                <option value="">All</option>
                                {Object.keys(ACTION_LABELS).map((action) => (
                                    <option key={action} value={action}>
                                        {ACTION_LABELS[action]}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <button
                            type="submit"
                            className="h-10 rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-900 hover:bg-neutral-50"
                        >
                            Apply filters
                        </button>
                    </form>

                    {logs.data.length === 0 ? (
                        <p className="py-8 text-center text-sm text-neutral-500">
                            No audit entries yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-neutral-100">
                            {logs.data.map((log) => (
                                <li
                                    key={log.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                                >
                                    <span className="flex min-w-0 items-center gap-2">
                                        <Badge
                                            variant={
                                                ACTOR_VARIANTS[
                                                    log.actor_type
                                                ] ?? 'neutral'
                                            }
                                        >
                                            {log.actor_type}
                                        </Badge>
                                        <span className="font-medium text-neutral-900">
                                            {ACTION_LABELS[log.action] ??
                                                log.action}
                                        </span>
                                        {log.subject_type === 'document' &&
                                        log.subject_id ? (
                                            <Link
                                                href={`/documents/${log.subject_id}`}
                                                className="text-blue-700 hover:underline"
                                            >
                                                document #{log.subject_id}
                                            </Link>
                                        ) : null}
                                    </span>
                                    <span className="flex items-center gap-3 text-xs text-neutral-500">
                                        {log.actor_name ? (
                                            <span>{log.actor_name}</span>
                                        ) : null}
                                        <span
                                            className="max-w-xs truncate"
                                            title={JSON.stringify(
                                                log.properties ?? {},
                                            )}
                                        >
                                            {Object.keys(log.properties ?? {})
                                                .length > 0
                                                ? Object.entries(
                                                      log.properties ?? {},
                                                  )
                                                      .slice(0, 2)
                                                      .map(
                                                          ([key, value]) =>
                                                              `${key}: ${String(value)}`,
                                                      )
                                                      .join(' · ')
                                                : ''}
                                        </span>
                                        <span className="tabular-nums">
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleString()}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {logs.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between text-xs text-neutral-500">
                            <span>
                                Page {logs.current_page} of {logs.last_page} ·{' '}
                                {logs.total} entries
                            </span>
                            <div className="flex gap-1">
                                {logs.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            className={`rounded border px-2 py-1 ${
                                                link.active
                                                    ? 'border-blue-600 bg-blue-50 text-blue-700'
                                                    : 'border-neutral-200 hover:bg-neutral-50'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : null,
                                )}
                            </div>
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}
