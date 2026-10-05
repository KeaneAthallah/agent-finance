import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type Props = InertiaPageProps & {
    scope: 'platform' | 'opd';
    documents: {
        total: number;
        by_status: Record<string, number>;
        by_fiscal_year: Record<string, number>;
        by_type: Record<string, number>;
    };
    confidence: { high: number; medium: number; low: number };
    workload: {
        review_required: number;
        open_anomalies: number;
        critical_anomalies: number;
        high_anomalies: number;
        resolved_anomalies: number;
        pending_sihandal_confirmations: number;
    };
    sync_health: { pending: number; success: number; failed: number };
    ai_cost: {
        calls: number;
        input_tokens: number;
        output_tokens: number;
        cost_estimate: string;
        avg_duration_ms: number;
    };
    ai_cost_by_model: {
        model: string;
        calls: number;
        input_tokens: number;
        output_tokens: number;
        cost_estimate: string;
    }[];
};

const CONFIDENCE_BARS: Record<string, string> = {
    green: 'bg-green-600',
    amber: 'bg-amber-600',
    red: 'bg-red-600',
};

function number(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-neutral-200 p-3">
            <div className="text-2xl font-semibold text-neutral-900">
                {value}
            </div>
            <div className="text-xs font-medium text-neutral-500">{label}</div>
        </div>
    );
}

export default function ReportsIndex({
    scope,
    documents,
    confidence,
    workload,
    sync_health: syncHealth,
    ai_cost: aiCost,
    ai_cost_by_model: aiCostByModel,
}: Props) {
    const confidenceTotal =
        confidence.high + confidence.medium + confidence.low;
    const share = (part: number) =>
        confidenceTotal === 0 ? 0 : Math.round((part / confidenceTotal) * 100);

    return (
        <AppShell title="Reports">
            <Head title="Reports" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Platform reports
                {scope === 'opd'
                    ? ' — scoped to your OPD (§36). Platform staff see every OPD.'
                    : ' — every OPD, platform-wide.'}{' '}
                Every figure is computed from real pipeline state.
            </p>

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>Documents processed</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Stat
                            label="Total documents"
                            value={number(documents.total)}
                        />
                        <Stat
                            label="Review required"
                            value={number(
                                documents.by_status.review_required ?? 0,
                            )}
                        />
                        <Stat
                            label="Approved"
                            value={number(
                                (documents.by_status.approved ?? 0) +
                                    (documents.by_status.completed ?? 0),
                            )}
                        />
                        <Stat
                            label="Failed"
                            value={number(
                                (documents.by_status.processing_failed ?? 0) +
                                    (documents.by_status.ocr_failed ?? 0) +
                                    (documents.by_status.ai_failed ?? 0) +
                                    (documents.by_status.validation_failed ??
                                        0) +
                                    (documents.by_status.sync_failed ?? 0),
                            )}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <h3 className="mb-2 text-sm font-medium text-neutral-700">
                                By fiscal year
                            </h3>
                            {Object.keys(documents.by_fiscal_year).length ===
                            0 ? (
                                <p className="text-sm text-neutral-500">
                                    No documents yet.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <tbody>
                                        {Object.entries(
                                            documents.by_fiscal_year,
                                        ).map(([year, total]) => (
                                            <tr
                                                key={year}
                                                className="border-b border-neutral-100"
                                            >
                                                <td className="py-1.5 font-medium text-neutral-900">
                                                    {year}
                                                </td>
                                                <td className="py-1.5 text-right text-neutral-600">
                                                    {number(total)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        <div>
                            <h3 className="mb-2 text-sm font-medium text-neutral-700">
                                By document type (top 5)
                            </h3>
                            {Object.keys(documents.by_type).length === 0 ? (
                                <p className="text-sm text-neutral-500">
                                    No documents yet.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <tbody>
                                        {Object.entries(documents.by_type).map(
                                            ([code, total]) => (
                                                <tr
                                                    key={code}
                                                    className="border-b border-neutral-100"
                                                >
                                                    <td className="py-1.5 font-medium text-neutral-900 uppercase">
                                                        {code}
                                                    </td>
                                                    <td className="py-1.5 text-right text-neutral-600">
                                                        {number(total)}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div className="mb-4 grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Extraction confidence</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        {confidenceTotal === 0 ? (
                            <p className="text-sm text-neutral-500">
                                No extraction fields scored yet.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {[
                                    {
                                        label: 'High (≥ 85)',
                                        count: confidence.high,
                                        variant: 'green' as const,
                                    },
                                    {
                                        label: 'Medium (60–84)',
                                        count: confidence.medium,
                                        variant: 'amber' as const,
                                    },
                                    {
                                        label: 'Low (< 60)',
                                        count: confidence.low,
                                        variant: 'red' as const,
                                    },
                                ].map((bucket) => (
                                    <div key={bucket.label}>
                                        <div className="mb-1 flex justify-between text-sm">
                                            <span className="font-medium text-neutral-700">
                                                {bucket.label}
                                            </span>
                                            <span className="text-neutral-500">
                                                {number(bucket.count)} (
                                                {share(bucket.count)}%)
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full bg-neutral-100">
                                            <div
                                                className={`h-2 rounded-full ${CONFIDENCE_BARS[bucket.variant]}`}
                                                style={{
                                                    width: `${share(bucket.count)}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Review workload</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-2 gap-3">
                            <Stat
                                label="Awaiting review"
                                value={number(workload.review_required)}
                            />
                            <Stat
                                label="Open anomalies"
                                value={number(workload.open_anomalies)}
                            />
                            <Stat
                                label="Critical / high"
                                value={`${number(workload.critical_anomalies)} / ${number(workload.high_anomalies)}`}
                            />
                            <Stat
                                label="SIHANDAL confirmations"
                                value={number(
                                    workload.pending_sihandal_confirmations,
                                )}
                            />
                        </div>
                        <p className="mt-3 text-xs text-neutral-500">
                            {number(workload.resolved_anomalies)} anomalies
                            resolved by reviewers.{' '}
                            <Link
                                className="font-medium text-blue-700 hover:underline"
                                href="/anomalies"
                            >
                                Open the anomaly workspace
                            </Link>
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="mb-4 grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>SIHANDAL sync health</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-3 gap-3">
                            <Stat
                                label="Pending"
                                value={number(syncHealth.pending)}
                            />
                            <Stat
                                label="Succeeded"
                                value={number(syncHealth.success)}
                            />
                            <Stat
                                label="Failed"
                                value={number(syncHealth.failed)}
                            />
                        </div>
                        <p className="mt-3 text-xs text-neutral-500">
                            Failed syncs are retryable — SIHANDAL being
                            unavailable never loses a document (§50).
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>AI token &amp; cost</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-2 gap-3">
                            <Stat
                                label="AI calls"
                                value={number(aiCost.calls)}
                            />
                            <Stat
                                label="Avg duration"
                                value={`${number(Math.round(aiCost.avg_duration_ms / 100) / 10)}s`}
                            />
                            <Stat
                                label="Input tokens"
                                value={number(aiCost.input_tokens)}
                            />
                            <Stat
                                label="Output tokens"
                                value={number(aiCost.output_tokens)}
                            />
                        </div>
                        <div className="mt-3 rounded-md border border-neutral-200 p-3">
                            <div className="text-xs font-medium text-neutral-500">
                                Estimated cost
                            </div>
                            <div className="text-xl font-semibold text-neutral-900">
                                {aiCost.cost_estimate}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Cost by model</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    {aiCostByModel.length === 0 ? (
                        <p className="text-sm text-neutral-500">
                            No AI usage recorded yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Model
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Calls
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Input tokens
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Output tokens
                                        </th>
                                        <th className="py-2 font-medium">
                                            Cost estimate
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {aiCostByModel.map((row) => (
                                        <tr
                                            key={row.model}
                                            className="border-b border-neutral-100"
                                        >
                                            <td className="py-2 pr-4 font-medium text-neutral-900">
                                                {row.model}
                                            </td>
                                            <td className="py-2 pr-4 text-neutral-600">
                                                {number(row.calls)}
                                            </td>
                                            <td className="py-2 pr-4 text-neutral-600">
                                                {number(row.input_tokens)}
                                            </td>
                                            <td className="py-2 pr-4 text-neutral-600">
                                                {number(row.output_tokens)}
                                            </td>
                                            <td className="py-2 text-neutral-600">
                                                {row.cost_estimate}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </AppShell>
    );
}
