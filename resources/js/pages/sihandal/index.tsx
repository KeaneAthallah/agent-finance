import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    EmptyState,
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
    auth: { can: { review: boolean } };
};

const SYNC_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red'
> = {
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
    auth,
}: Props) {
    return (
        <AppShell title="SIHANDAL">
            <Head title="SIHANDAL integration" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Match candidates from the SIHANDAL system of record, ordered by
                confidence. Confirming a candidate syncs the approved document
                through the API; rejections are recorded. Sync history keeps the
                full request/response of every write.
            </p>

            <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <p className="text-2xl font-semibold text-neutral-900 tabular-nums">
                            {counts.pending_matches}
                        </p>
                        <p className="text-xs text-neutral-500">
                            pending match candidates
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <p className="text-2xl font-semibold text-neutral-900 tabular-nums">
                            {counts.pending_syncs}
                        </p>
                        <p className="text-xs text-neutral-500">
                            pending syncs (retryable)
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <p className="text-2xl font-semibold text-neutral-900 tabular-nums">
                            {counts.failed_syncs}
                        </p>
                        <p className="text-xs text-neutral-500">failed syncs</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <p className="text-2xl font-semibold text-neutral-900 tabular-nums">
                            {counts.successful_syncs}
                        </p>
                        <p className="text-xs text-neutral-500">
                            successful syncs
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Pending match candidates</CardTitle>
                    <p className="mt-0.5 text-xs text-neutral-500">
                        Highest confidence first — decide in the document
                        workspace
                    </p>
                </CardHeader>
                <CardContent className="p-4">
                    {pendingMatches.data.length === 0 ? (
                        <EmptyState
                            title="No pending candidates"
                            description="Match candidates appear here once a document completes validation and SIHANDAL returns possible matches."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Confidence
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            SIHANDAL reference
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            OPD
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
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
                                            className="border-b border-neutral-100 last:border-0"
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
                                            <td className="py-2 pr-4">
                                                <span className="font-medium text-neutral-900">
                                                    {
                                                        match.document
                                                            .original_filename
                                                    }
                                                </span>
                                                {match.document
                                                    .document_number ? (
                                                    <span className="ml-2 text-xs text-neutral-500">
                                                        {
                                                            match.document
                                                                .document_number
                                                        }
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <span className="text-neutral-700">
                                                    {String(
                                                        match.sihandal_data
                                                            .uraian ??
                                                            match.entity_type,
                                                    )}
                                                </span>
                                                <span className="ml-2 text-xs text-neutral-500">
                                                    {String(
                                                        match.sihandal_data
                                                            .rekening ??
                                                            match.entity_id,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="py-2 pr-4">
                                                {match.document.opd?.name ??
                                                    '—'}
                                            </td>
                                            <td className="py-2 pr-4 text-xs text-neutral-500">
                                                {new Date(
                                                    match.created_at,
                                                ).toLocaleString()}
                                            </td>
                                            <td className="py-2 text-right">
                                                <Link
                                                    href={`/documents/${match.document.id}`}
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            !auth.can.review
                                                        }
                                                    >
                                                        {auth.can.review
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
                        <PaginationLinks links={pendingMatches.links} />
                    ) : null}
                </CardContent>
            </Card>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Sync history</CardTitle>
                    <p className="mt-0.5 text-xs text-neutral-500">
                        Every write to SIHANDAL with its full request/response
                    </p>
                </CardHeader>
                <CardContent className="p-4">
                    {syncs.data.length === 0 ? (
                        <EmptyState
                            title="No syncs yet"
                            description="Confirmed candidates on approved documents are pushed to SIHANDAL and recorded here."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Endpoint
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            HTTP
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            By
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Attempts
                                        </th>
                                        <th className="py-2 font-medium">At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {syncs.data.map((sync) => (
                                        <tr
                                            key={sync.id}
                                            className="border-b border-neutral-100 last:border-0"
                                        >
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant={
                                                        SYNC_VARIANTS[
                                                            sync.status
                                                        ] ?? 'neutral'
                                                    }
                                                >
                                                    {sync.status}
                                                </Badge>
                                            </td>
                                            <td className="py-2 pr-4">
                                                <span className="font-medium text-neutral-900">
                                                    {
                                                        sync.document
                                                            .original_filename
                                                    }
                                                </span>
                                            </td>
                                            <td className="py-2 pr-4 font-mono text-xs text-neutral-700">
                                                {sync.method} {sync.endpoint}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {sync.http_status?.toString() ??
                                                    '—'}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {sync.user?.name ?? '—'}
                                            </td>
                                            <td className="py-2 pr-4 tabular-nums">
                                                {sync.attempts}
                                            </td>
                                            <td className="py-2 text-xs text-neutral-500">
                                                {new Date(
                                                    sync.created_at,
                                                ).toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {syncs.last_page > 1 ? (
                        <PaginationLinks links={syncs.links} />
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}

function PaginationLinks({
    links,
}: {
    links: { url: string | null; label: string; active: boolean }[];
}) {
    return (
        <div className="mt-4 flex items-center justify-end gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        className={`rounded border px-2 py-1 text-xs ${
                            link.active
                                ? 'border-blue-600 bg-blue-50 text-blue-700'
                                : 'border-neutral-200 hover:bg-neutral-50'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : null,
            )}
        </div>
    );
}
