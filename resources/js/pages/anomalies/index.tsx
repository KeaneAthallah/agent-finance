import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import AnomalyController from '@/actions/App/Http/Controllers/AnomalyController';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    AlertCircleIcon,
    BuildingIcon,
    CheckCircleIcon,
    InfoIcon,
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
import { cn } from '@/lib/utils';
import type { InertiaPageProps } from '@/types';

const SEVERITY_ACCENT: Record<string, string> = {
    critical: 'border-l-danger',
    high: 'border-l-danger',
    warning: 'border-l-warning',
    info: 'border-l-info',
};

type Anomaly = {
    id: number;
    type: string;
    severity: string;
    title: string;
    detail: string | null;
    evidence: Record<string, unknown> | null;
    created_at: string;
    document: {
        id: number;
        document_number: string | null;
        original_filename: string;
        fiscal_year: number | null;
        status: string;
        document_type?: { id: number; code: string; name: string } | null;
        opd?: { id: number; code: string; name: string } | null;
    };
};

type Props = InertiaPageProps & {
    anomalies: {
        data: Anomaly[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { severity: string | null; type: string | null };
    severities: string[];
    types: string[];
    can: { review: boolean };
};

const SEVERITY_VARIANTS: Record<string, 'blue' | 'amber' | 'red'> = {
    info: 'blue',
    warning: 'amber',
    high: 'red',
    critical: 'red',
};

const TYPE_LABELS: Record<string, string> = {
    total_mismatch: 'Total mismatch',
    fiscal_year_mismatch: 'Fiscal year mismatch',
    missing_document_number: 'Missing document number',
    low_confidence: 'Low confidence',
    duplicate: 'Duplicate',
};

function typeLabel(type: string): string {
    return TYPE_LABELS[type] ?? type.replace(/_/g, ' ');
}

export default function AnomaliesIndex({
    anomalies,
    filters,
    severities,
    types,
    can,
}: Props) {
    function filter(key: 'severity' | 'type', value: string) {
        router.get(
            AnomalyController.index({
                query: {
                    severity: filters.severity,
                    type: filters.type,
                    [key]: value !== '' ? value : null,
                },
            }).url,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    function decide(anomalyId: number, decision: 'resolve' | 'dismiss') {
        router.post(
            (decision === 'resolve'
                ? AnomalyController.resolve(anomalyId)
                : AnomalyController.dismiss(anomalyId)
            ).url,
            {},
            { preserveScroll: true },
        );
    }

    const rows = useMemo(
        () =>
            [...anomalies.data].sort((a, b) => {
                const rank =
                    (SEVERITY_RANK[a.severity] ?? 99) -
                    (SEVERITY_RANK[b.severity] ?? 99);

                return rank !== 0
                    ? rank
                    : new Date(b.created_at).getTime() -
                          new Date(a.created_at).getTime();
            }),
        [anomalies.data],
    );

    return (
        <AppShell title="Anomalies" subtitle="Validation engine indicators">
            <Head title="Anomalies" />

            <PageHeader
                title="Anomalies"
                description="Factual anomaly indicators from the validation engine — mismatches, duplicates, unknown references. Indicators, never verdicts: a human decides what each one means. Open anomalies block automatic approval."
            />

            {/* Filters */}
            <Card className="mb-5">
                <CardContent className="grid items-end gap-3 p-4 md:grid-cols-3">
                    <div>
                        <Label htmlFor="filter-severity">Severity</Label>
                        <Select
                            id="filter-severity"
                            value={filters.severity ?? ''}
                            onChange={(event) =>
                                filter('severity', event.target.value)
                            }
                        >
                            <option value="">All severities</option>
                            {severities.map((severity) => (
                                <option key={severity} value={severity}>
                                    {severity}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="filter-type">Type</Label>
                        <Select
                            id="filter-type"
                            value={filters.type ?? ''}
                            onChange={(event) =>
                                filter('type', event.target.value)
                            }
                        >
                            <option value="">All types</option>
                            {types.map((type) => (
                                <option key={type} value={type}>
                                    {typeLabel(type)}
                                </option>
                            ))}
                        </Select>
                    </div>
                    {(filters.severity !== null || filters.type !== null) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.get(
                                    AnomalyController.index().url,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                    },
                                )
                            }
                        >
                            Reset
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-4">
                    <div className="mb-3 text-sm text-muted tabular-nums">
                        {anomalies.total} open indicator
                        {anomalies.total === 1 ? '' : 's'}
                    </div>

                    {rows.length === 0 ? (
                        <EmptyState
                            icon={<CheckCircleIcon size={20} />}
                            title="No open anomalies"
                            description="Documents that pass every validation rule never appear here."
                        />
                    ) : (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-sunken/50 text-left text-[11px] font-medium tracking-wider text-subtle uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Severity
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Indicator
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium md:table-cell">
                                            OPD
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium lg:table-cell">
                                            TA
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium sm:table-cell">
                                            Age
                                        </th>
                                        <th className="py-2 font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((anomaly) => (
                                        <tr
                                            key={anomaly.id}
                                            className="border-b border-border align-top transition-colors last:border-0 hover:bg-elevated"
                                        >
                                            <td
                                                className={cn(
                                                    'border-l-2 py-2 pr-4',
                                                    SEVERITY_ACCENT[
                                                        anomaly.severity
                                                    ] ?? 'border-l-transparent',
                                                )}
                                            >
                                                <Badge
                                                    variant={
                                                        SEVERITY_VARIANTS[
                                                            anomaly.severity
                                                        ] ?? 'blue'
                                                    }
                                                >
                                                    {anomaly.severity ===
                                                    'critical' ? (
                                                        <AlertCircleIcon
                                                            size={12}
                                                        />
                                                    ) : (
                                                        <InfoIcon size={12} />
                                                    )}
                                                    {anomaly.severity}
                                                </Badge>
                                            </td>
                                            <td className="py-2 pr-4">
                                                <span className="text-muted">
                                                    {typeLabel(anomaly.type)}
                                                </span>
                                            </td>
                                            <td className="max-w-xs py-2 pr-4">
                                                <span className="font-medium text-foreground">
                                                    {anomaly.title}
                                                </span>
                                                {anomaly.detail !== null && (
                                                    <span className="mt-0.5 block text-xs text-muted">
                                                        {anomaly.detail}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="max-w-[240px] py-2 pr-4">
                                                <Link
                                                    className="block truncate font-medium text-brand hover:underline"
                                                    title={
                                                        anomaly.document
                                                            .original_filename
                                                    }
                                                    href={DocumentController.show.url(
                                                        {
                                                            document:
                                                                anomaly.document
                                                                    .id,
                                                        },
                                                    )}
                                                >
                                                    {
                                                        anomaly.document
                                                            .original_filename
                                                    }
                                                </Link>
                                                <span className="block truncate text-xs text-subtle">
                                                    {anomaly.document
                                                        .document_number ??
                                                        'no number'}
                                                </span>
                                            </td>
                                            <td className="hidden max-w-[140px] py-2 pr-4 md:table-cell">
                                                <span className="inline-flex items-center gap-1.5 text-muted">
                                                    <BuildingIcon size={13} />
                                                    <span className="truncate">
                                                        {anomaly.document.opd
                                                            ?.name ?? '—'}
                                                    </span>
                                                </span>
                                            </td>
                                            <td className="hidden py-2 pr-4 text-muted tabular-nums lg:table-cell">
                                                {anomaly.document.fiscal_year ??
                                                    '—'}
                                            </td>
                                            <td className="hidden py-2 pr-4 text-xs text-subtle tabular-nums sm:table-cell">
                                                {new Date(
                                                    anomaly.created_at,
                                                ).toLocaleDateString('id-ID')}
                                            </td>
                                            <td className="py-2">
                                                {can.review ? (
                                                    <div className="flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                decide(
                                                                    anomaly.id,
                                                                    'resolve',
                                                                )
                                                            }
                                                        >
                                                            <CheckCircleIcon
                                                                size={13}
                                                            />
                                                            Resolve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                decide(
                                                                    anomaly.id,
                                                                    'dismiss',
                                                                )
                                                            }
                                                        >
                                                            Dismiss
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-subtle">
                                                        read-only
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {anomalies.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between gap-3">
                            <p className="text-xs text-subtle tabular-nums">
                                Page {anomalies.current_page} of{' '}
                                {anomalies.last_page}
                            </p>
                            <PaginationLinks links={anomalies.links} />
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}

const SEVERITY_RANK: Record<string, number> = {
    critical: 0,
    high: 1,
    warning: 2,
    info: 3,
};
