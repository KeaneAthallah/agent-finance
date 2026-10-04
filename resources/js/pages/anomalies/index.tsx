import { Head, Link, router } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import AnomalyController from '@/actions/App/Http/Controllers/AnomalyController';
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
    auth: { can: { review: boolean } };
};

const SEVERITY_VARIANTS: Record<string, 'neutral' | 'blue' | 'amber' | 'red'> =
    {
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
    auth,
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

    return (
        <AppShell title="Anomalies">
            <Head title="Anomalies" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Factual anomaly indicators from the validation engine —
                mismatches, duplicates, unknown references. Indicators, never
                verdicts: a human decides what each one means. Open anomalies
                block auto-approval.
            </p>

            <Card className="mb-4">
                <CardContent className="flex items-end gap-4 p-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-500">
                            Severity
                        </label>
                        <select
                            className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm"
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
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-neutral-500">
                            Type
                        </label>
                        <select
                            className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm"
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
                        </select>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Open anomalies ({anomalies.total})</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    {anomalies.data.length === 0 ? (
                        <EmptyState
                            title="No open anomalies"
                            description="Documents that pass every validation rule never appear here."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
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
                                        <th className="py-2 pr-4 font-medium">
                                            OPD
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            TA
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Age
                                        </th>
                                        <th className="py-2 font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {anomalies.data.map((anomaly) => (
                                        <tr
                                            key={anomaly.id}
                                            className="border-b border-neutral-100 align-top"
                                        >
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant={
                                                        SEVERITY_VARIANTS[
                                                            anomaly.severity
                                                        ] ?? 'neutral'
                                                    }
                                                >
                                                    {anomaly.severity}
                                                </Badge>
                                            </td>
                                            <td className="py-2 pr-4">
                                                {typeLabel(anomaly.type)}
                                            </td>
                                            <td className="max-w-xs py-2 pr-4">
                                                <span className="font-medium text-neutral-900">
                                                    {anomaly.title}
                                                </span>
                                                {anomaly.detail !== null && (
                                                    <span className="mt-0.5 block text-xs text-neutral-500">
                                                        {anomaly.detail}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <Link
                                                    className="font-medium text-blue-700 hover:underline"
                                                    href={`/documents/${anomaly.document.id}`}
                                                >
                                                    {
                                                        anomaly.document
                                                            .original_filename
                                                    }
                                                </Link>
                                                <span className="mt-0.5 block text-xs text-neutral-500">
                                                    {anomaly.document
                                                        .document_number ??
                                                        'no number'}
                                                </span>
                                            </td>
                                            <td className="py-2 pr-4">
                                                {anomaly.document.opd?.code ??
                                                    '—'}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {anomaly.document.fiscal_year ??
                                                    '—'}
                                            </td>
                                            <td className="py-2 pr-4 text-xs text-neutral-500">
                                                {new Date(
                                                    anomaly.created_at,
                                                ).toLocaleDateString('id-ID')}
                                            </td>
                                            <td className="py-2">
                                                {auth.can.review ? (
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
                                                    <span className="text-xs text-neutral-400">
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

                    {anomalies.last_page > 1 && (
                        <div className="mt-4 flex gap-2">
                            {anomalies.links.map((link, index) =>
                                link.url === null ? null : (
                                    <button
                                        key={index}
                                        className={`h-8 min-w-8 rounded-md px-2 text-sm ${
                                            link.active
                                                ? 'bg-blue-700 text-white'
                                                : 'border border-neutral-300 bg-white text-neutral-700'
                                        }`}
                                        onClick={() =>
                                            router.get(link.url ?? '')
                                        }
                                    >
                                        {link.label.replace(
                                            /&laquo;|&raquo;/g,
                                            '',
                                        )}
                                    </button>
                                ),
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
        </AppShell>
    );
}
