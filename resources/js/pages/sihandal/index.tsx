import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    CheckCircleIcon,
    ClockIcon,
    InfoIcon,
    RefreshIcon,
    SihandalIcon,
} from '@/components/icons';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    EmptyState,
    PageHeader,
    PaginationLinks,
    StatCard,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type PendingMatch = {
    id: number;
    version: number;
    entity_type: string;
    entity_id: string;
    sihandal_data: Record<string, string | number | null>;
    confidence: number;
    created_at: string;
    document: {
        id: number;
        original_filename: string;
        document_number: string | null;
        status: string;
        opd?: { id: number; code: string; name: string } | null;
    };
};

type SyncRecord = {
    id: number;
    endpoint: string;
    method: string;
    http_status: number | null;
    status: string;
    error: string | null;
    attempts: number;
    created_at: string;
    document: {
        id: number;
        original_filename: string;
        document_number: string | null;
    };
    user?: { id: number; name: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = InertiaPageProps & {
    counts: Record<string, number>;
    pendingMatches: Paginated<PendingMatch>;
    syncs: Paginated<SyncRecord>;
    can: { review: boolean };
};

const SYNC_VARIANTS: Record<string, 'amber' | 'green' | 'red'> = {
    pending: 'amber',
    success: 'green',
    failed: 'red',
};

function confidenceVariant(confidence: number): 'green' | 'amber' | 'red' {
    if (confidence >= 85) {
        return 'green';
    }

    if (confidence >= 60) {
        return 'amber';
    }

    return 'red';
}

export default function SihandalIndex({
    counts,
    pendingMatches,
    syncs,
    can,
}: Props) {
    const lastSync = syncs.data[0] ?? null;

    const syncRate = useMemo(() => {
        const total = counts.successful_syncs + counts.failed_syncs;

        return total === 0
            ? null
            : Math.round((counts.successful_syncs / total) * 100);
    }, [counts]);

    return (
        <AppShell title="SIHANDAL" subtitle="System of record integration">
            <Head title="SIHANDAL integration" />

            <PageHeader
                title="SIHANDAL integration"
                description="Match candidates from the SIHANDAL system of record, ordered by confidence. Confirming a candidate syncs the approved document through the API; rejections are recorded. Sync history keeps the full request/response of every write."
            />

            {/* Integration status */}
            <Card className="mb-5">
                <CardContent className="grid items-start gap-4 p-4 sm:grid-cols-[1fr_auto]">
                    <div className="flex items-start gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-subtle text-purple">
                            <SihandalIcon size={20} />
                        </span>
                        <div>
                            <p className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <span className="flex h-2 w-2 rounded-full bg-success">
                                    <span className="sr-only">
                                        Integration active
                                    </span>
                                </span>
                                SIHANDAL V2 — connected via REST API
                            </p>
                            <p className="mt-0.5 text-sm text-muted">
                                Agent Finance never writes to the SIHANDAL
                                database directly — every read and write goes
                                through the API with the configured bearer
                                token.
                            </p>
                        </div>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-8 gap-y-1 text-sm sm:min-w-56">
                        <dt className="text-muted">Last sync</dt>
                        <dd className="text-right font-medium text-foreground tabular-nums">
                            {lastSync
                                ? new Date(lastSync.created_at).toLocaleString(
                                      'id-ID',
                                  )
                                : '—'}
                        </dd>
                        <dt className="text-muted">Success rate</dt>
                        <dd className="text-right font-medium text-foreground tabular-nums">
                            {syncRate == null ? '—' : `${syncRate}%`}
                        </dd>
                    </dl>
                </CardContent>
            </Card>

            {/* Counters */}
            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Pending candidates"
                    value={counts.pending_matches}
                    hint="awaiting a decision"
                    variant="amber"
                    icon={<ClockIcon size={15} />}
                />
                <StatCard
                    label="Pending syncs"
                    value={counts.pending_syncs}
                    hint="retryable"
                    icon={<RefreshIcon size={15} />}
                />
                <StatCard
                    label="Failed syncs"
                    value={counts.failed_syncs}
                    hint="need attention"
                    variant="red"
                    icon={<SihandalIcon size={15} />}
                />
                <StatCard
                    label="Successful syncs"
                    value={counts.successful_syncs}
                    hint="written to SIHANDAL"
                    variant="green"
                    icon={<CheckCircleIcon size={15} />}
                />
            </div>

            {/* Pending match candidates */}
            <Card>
                <CardHeader>
                    <CardTitle>Pending match candidates</CardTitle>
                    <CardDescription>
                        Highest confidence first — decide in the document
                        workspace
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-4">
                    {pendingMatches.data.length === 0 ? (
                        <EmptyState
                            icon={<SihandalIcon size={20} />}
                            title="No pending candidates"
                            description="Match candidates appear here once a document completes validation and SIHANDAL returns possible matches."
                        />
                    ) : (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[780px] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-subtle uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Confidence
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            SIHANDAL reference
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium md:table-cell">
                                            OPD
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium lg:table-cell">
                                            Matched at
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pendingMatches.data.map((match) => (
                                        <tr
                                            key={match.id}
                                            className="border-b border-border last:border-0 hover:bg-elevated"
                                        >
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant={confidenceVariant(
                                                        match.confidence,
                                                    )}
                                                >
                                                    {match.confidence}%
                                                </Badge>
                                            </td>
                                            <td className="max-w-[240px] py-2 pr-4">
                                                <Link
                                                    href={DocumentController.show.url(
                                                        {
                                                            document:
                                                                match.document
                                                                    .id,
                                                        },
                                                    )}
                                                    className="block truncate font-medium text-brand hover:underline"
                                                    title={
                                                        match.document
                                                            .original_filename
                                                    }
                                                >
                                                    {
                                                        match.document
                                                            .original_filename
                                                    }
                                                </Link>
                                                {match.document
                                                    .document_number ? (
                                                    <span className="block truncate text-xs text-subtle">
                                                        {
                                                            match.document
                                                                .document_number
                                                        }
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="max-w-[280px] py-2 pr-4">
                                                <span className="block truncate text-foreground">
                                                    {String(
                                                        match.sihandal_data
                                                            .uraian ??
                                                            match.entity_type,
                                                    )}
                                                </span>
                                                <span className="block truncate text-xs text-subtle">
                                                    {String(
                                                        match.sihandal_data
                                                            .rekening ??
                                                            match.entity_id,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="hidden max-w-[160px] py-2 pr-4 md:table-cell">
                                                <span className="truncate text-muted">
                                                    {match.document.opd?.name ??
                                                        '—'}
                                                </span>
                                            </td>
                                            <td className="hidden py-2 pr-4 text-xs text-subtle tabular-nums lg:table-cell">
                                                {new Date(
                                                    match.created_at,
                                                ).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-2 text-right">
                                                <Link
                                                    href={DocumentController.show.url(
                                                        {
                                                            document:
                                                                match.document
                                                                    .id,
                                                        },
                                                    )}
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={!can.review}
                                                    >
                                                        {can.review
                                                            ? 'Decide'
                                                            : 'View'}
                                                    </Button>
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pendingMatches.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between gap-3">
                            <p className="text-xs text-subtle tabular-nums">
                                Page {pendingMatches.current_page} of{' '}
                                {pendingMatches.last_page}
                            </p>
                            <PaginationLinks links={pendingMatches.links} />
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            {/* Sync history */}
            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Sync history</CardTitle>
                    <CardDescription>
                        Every write to SIHANDAL with its full request/response
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-4">
                    {syncs.data.length === 0 ? (
                        <EmptyState
                            icon={<InfoIcon size={20} />}
                            title="No syncs yet"
                            description="Confirmed candidates on approved documents are pushed to SIHANDAL and recorded here."
                        />
                    ) : (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-subtle uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Endpoint
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium sm:table-cell">
                                            HTTP
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium md:table-cell">
                                            By
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium lg:table-cell">
                                            Attempts
                                        </th>
                                        <th className="py-2 font-medium">At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {syncs.data.map((sync) => (
                                        <tr
                                            key={sync.id}
                                            className="border-b border-border last:border-0 hover:bg-elevated"
                                        >
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant={
                                                        SYNC_VARIANTS[
                                                            sync.status
                                                        ] ?? 'amber'
                                                    }
                                                >
                                                    {sync.status}
                                                </Badge>
                                            </td>
                                            <td className="max-w-[220px] py-2 pr-4">
                                                <Link
                                                    href={DocumentController.show.url(
                                                        {
                                                            document:
                                                                sync.document
                                                                    .id,
                                                        },
                                                    )}
                                                    className="block truncate font-medium text-brand hover:underline"
                                                    title={
                                                        sync.document
                                                            .original_filename
                                                    }
                                                >
                                                    {
                                                        sync.document
                                                            .original_filename
                                                    }
                                                </Link>
                                            </td>
                                            <td className="py-2 pr-4">
                                                <code className="text-xs text-foreground">
                                                    {sync.method}{' '}
                                                    {sync.endpoint}
                                                </code>
                                                {sync.error ? (
                                                    <p
                                                        className="mt-0.5 max-w-56 truncate text-xs text-danger"
                                                        title={sync.error}
                                                    >
                                                        {sync.error}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="hidden py-2 pr-4 text-muted tabular-nums sm:table-cell">
                                                {sync.http_status?.toString() ??
                                                    '—'}
                                            </td>
                                            <td className="hidden py-2 pr-4 text-muted md:table-cell">
                                                {sync.user?.name ?? '—'}
                                            </td>
                                            <td className="hidden py-2 pr-4 text-muted tabular-nums lg:table-cell">
                                                {sync.attempts}
                                            </td>
                                            <td className="py-2 text-xs text-subtle tabular-nums">
                                                {new Date(
                                                    sync.created_at,
                                                ).toLocaleString('id-ID')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {syncs.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between gap-3">
                            <p className="text-xs text-subtle tabular-nums">
                                Page {syncs.current_page} of {syncs.last_page} ·{' '}
                                {syncs.total} syncs
                            </p>
                            <PaginationLinks links={syncs.links} />
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}
