import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import {
    Badge,
    Button,
    Card,
    CardContent,
    EmptyState,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type DocumentItem = {
    id: number;
    status: string;
    original_filename: string;
    file_size_human: string;
    fiscal_year: number | null;
    document_number: string | null;
    document_type?: { id: number; code: string; name: string } | null;
    opd?: { id: number; code: string; name: string } | null;
    uploader?: { id: number; name: string } | null;
    created_at: string;
};

type Props = InertiaPageProps & {
    documents: {
        data: (DocumentItem & {
            open_anomalies_count: number;
            last_reviewed_at: string | null;
        })[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    openAnomalies: Record<string, number>;
    worstSeverities: Record<string, string>;
    auth: { can: { review: boolean } };
};

const SEVERITY_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red' | 'purple'
> = {
    info: 'neutral',
    warning: 'amber',
    high: 'red',
    critical: 'red',
};

export default function ReviewIndex({
    documents,
    openAnomalies,
    worstSeverities,
    auth,
}: Props) {
    return (
        <AppShell title="Review Queue">
            <Head title="Review queue" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Documents flagged by the validation engine — blocking anomalies
                or low-confidence extraction — wait here for a human decision.
                Clean documents are approved automatically and never appear.
            </p>

            <Card>
                <CardContent className="p-4">
                    {documents.data.length === 0 ? (
                        <EmptyState
                            title="Review queue is empty"
                            description="No documents currently require human review. Flagged documents appear here ordered by submission time."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            OPD
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Anomalies
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Waiting since
                                        </th>
                                        <th className="py-2 font-medium" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {documents.data.map((document) => {
                                        const openCount =
                                            openAnomalies[
                                                String(document.id)
                                            ] ?? document.open_anomalies_count;
                                        const severity =
                                            worstSeverities[
                                                String(document.id)
                                            ];

                                        return (
                                            <tr
                                                key={document.id}
                                                className="border-b border-neutral-100 hover:bg-neutral-50"
                                            >
                                                <td className="py-2 pr-4">
                                                    <Link
                                                        href={`/documents/${document.id}`}
                                                        className="font-medium text-blue-700 hover:underline"
                                                    >
                                                        {
                                                            document.original_filename
                                                        }
                                                    </Link>
                                                    {document.document_number ? (
                                                        <p className="text-xs text-neutral-500">
                                                            {
                                                                document.document_number
                                                            }
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {document.document_type
                                                        ?.name ?? '—'}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {document.opd?.name ??
                                                        '—'}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {openCount > 0 ? (
                                                        <span className="flex items-center gap-1.5">
                                                            <span className="tabular-nums">
                                                                {
                                                                    openCount
                                                                }
                                                            </span>
                                                            {severity ? (
                                                                <Badge
                                                                    variant={
                                                                        SEVERITY_VARIANTS[
                                                                            severity
                                                                        ] ??
                                                                        'neutral'
                                                                    }
                                                                >
                                                                    {severity}
                                                                </Badge>
                                                            ) : null}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="py-2 pr-4 text-xs text-neutral-500">
                                                    {new Date(
                                                        document.validated_at ??
                                                            document.created_at,
                                                    ).toLocaleDateString()}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <Link
                                                        href={`/documents/${document.id}`}
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                !auth.can.review
                                                            }
                                                        >
                                                            {auth.can.review
                                                                ? 'Review'
                                                                : 'View'}
                                                        </Button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {documents.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between text-xs text-neutral-500">
                            <span>
                                Page {documents.current_page} of{' '}
                                {documents.last_page} · {documents.total}{' '}
                                awaiting review
                            </span>
                            <div className="flex gap-1">
                                {documents.links.map((link, index) =>
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
