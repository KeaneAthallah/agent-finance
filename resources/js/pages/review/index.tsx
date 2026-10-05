import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    AlertCircleIcon,
    BuildingIcon,
    FileIcon,
    InfoIcon,
    ReviewIcon,
} from '@/components/icons';
import {
    Badge,
    Button,
    Card,
    CardContent,
    EmptyState,
    PageHeader,
    PaginationLinks,
    StatCard,
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
    validated_at: string | null;
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
    can: { review: boolean };
};

const SEVERITY_VARIANTS: Record<string, 'blue' | 'amber' | 'red'> = {
    info: 'blue',
    warning: 'amber',
    high: 'red',
    critical: 'red',
};

const SEVERITY_RANK: Record<string, number> = {
    critical: 0,
    high: 1,
    warning: 2,
    info: 3,
};

function severityRank(severity: string | undefined): number {
    return severity !== undefined && severity in SEVERITY_RANK
        ? SEVERITY_RANK[severity]
        : 99;
}

export default function ReviewIndex({
    documents,
    openAnomalies,
    worstSeverities,
    can,
}: Props) {
    // Highest-risk documents first — the queue is a
    // triage surface, not just a list.
    const rows = useMemo(
        () =>
            [...documents.data].sort((a, b) => {
                const severityA = worstSeverities[String(a.id)];
                const severityB = worstSeverities[String(b.id)];
                const rank = severityRank(severityA) - severityRank(severityB);

                if (rank !== 0) {
                    return rank;
                }

                const anomalies =
                    (openAnomalies[String(b.id)] ?? b.open_anomalies_count) -
                    (openAnomalies[String(a.id)] ?? a.open_anomalies_count);

                if (anomalies !== 0) {
                    return anomalies;
                }

                return (
                    new Date(a.validated_at ?? a.created_at).getTime() -
                    new Date(b.validated_at ?? b.created_at).getTime()
                );
            }),
        [documents.data, openAnomalies, worstSeverities],
    );

    const severityCounts = useMemo(() => {
        const counts = { critical: 0, high: 0, warning: 0, info: 0 };

        for (const severity of Object.values(worstSeverities)) {
            if (severity in counts) {
                counts[severity as keyof typeof counts] += 1;
            }
        }

        return counts;
    }, [worstSeverities]);

    return (
        <AppShell
            title="Review Queue"
            subtitle="Human decisions on flagged documents"
        >
            <Head title="Review queue" />

            <PageHeader
                title="Review queue"
                description="Documents flagged by the validation engine — blocking anomalies or low-confidence extraction — wait here for a human decision. Clean documents are approved automatically and never appear."
            />

            {/* Priority snapshot */}
            <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                    label="Awaiting review"
                    value={documents.total}
                    hint="documents in queue"
                    variant="amber"
                    icon={<ReviewIcon size={15} />}
                />
                <StatCard
                    label="Critical / high"
                    value={severityCounts.critical + severityCounts.high}
                    hint="need urgent attention"
                    variant="red"
                    icon={<AlertCircleIcon size={15} />}
                />
                <StatCard
                    label="Warnings"
                    value={severityCounts.warning}
                    hint="moderate risk"
                    variant="blue"
                    icon={<InfoIcon size={15} />}
                />
                <StatCard
                    label="Info"
                    value={severityCounts.info}
                    hint="lowest priority"
                    variant="neutral"
                    icon={<ReviewIcon size={15} />}
                />
            </div>

            <Card>
                <CardContent className="p-4">
                    {rows.length === 0 ? (
                        <EmptyState
                            icon={<ReviewIcon size={20} />}
                            title="Review queue is empty"
                            description="No documents currently require human review. Flagged documents appear here, ordered by risk."
                        />
                    ) : (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-subtle uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Priority
                                        </th>
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
                                        <th className="hidden py-2 pr-4 font-medium md:table-cell">
                                            Waiting since
                                        </th>
                                        <th className="py-2 font-medium">
                                            <span className="sr-only">
                                                Action
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((document) => {
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
                                                className="border-b border-border last:border-0 hover:bg-elevated"
                                            >
                                                <td className="py-2 pr-4">
                                                    {severity ? (
                                                        <Badge
                                                            variant={
                                                                SEVERITY_VARIANTS[
                                                                    severity
                                                                ] ?? 'amber'
                                                            }
                                                        >
                                                            {severity ===
                                                            'critical' ? (
                                                                <AlertCircleIcon
                                                                    size={12}
                                                                />
                                                            ) : (
                                                                <InfoIcon
                                                                    size={12}
                                                                />
                                                            )}
                                                            {severity}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-xs text-subtle">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="max-w-[280px] py-2 pr-4">
                                                    <Link
                                                        href={DocumentController.show.url(
                                                            {
                                                                document:
                                                                    document.id,
                                                            },
                                                        )}
                                                        className="block truncate font-medium text-brand hover:underline"
                                                        title={
                                                            document.original_filename
                                                        }
                                                    >
                                                        {
                                                            document.original_filename
                                                        }
                                                    </Link>
                                                    {document.document_number ? (
                                                        <p className="truncate text-xs text-subtle">
                                                            {
                                                                document.document_number
                                                            }
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    <span className="inline-flex items-center gap-1.5 text-muted">
                                                        <FileIcon size={13} />
                                                        <span className="truncate">
                                                            {document
                                                                .document_type
                                                                ?.name ?? '—'}
                                                        </span>
                                                    </span>
                                                </td>
                                                <td className="max-w-[160px] py-2 pr-4">
                                                    <span className="inline-flex items-center gap-1.5 text-muted">
                                                        <BuildingIcon
                                                            size={13}
                                                        />
                                                        <span className="truncate">
                                                            {document.opd
                                                                ?.name ?? '—'}
                                                        </span>
                                                    </span>
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {openCount > 0 ? (
                                                        <span className="inline-flex items-center gap-1.5 text-foreground tabular-nums">
                                                            <span className="font-semibold">
                                                                {openCount}
                                                            </span>
                                                            <span className="text-xs text-subtle">
                                                                open
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-subtle">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="hidden py-2 pr-4 text-xs text-subtle tabular-nums md:table-cell">
                                                    {new Date(
                                                        document.validated_at ??
                                                            document.created_at,
                                                    ).toLocaleDateString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <Link
                                                        href={DocumentController.show.url(
                                                            {
                                                                document:
                                                                    document.id,
                                                            },
                                                        )}
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                !can.review
                                                            }
                                                        >
                                                            {can.review
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
                        <div className="mt-4 flex items-center justify-between gap-3">
                            <p className="text-xs text-subtle tabular-nums">
                                Page {documents.current_page} of{' '}
                                {documents.last_page} · {documents.total}{' '}
                                awaiting review
                            </p>
                            <PaginationLinks links={documents.links} />
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}
