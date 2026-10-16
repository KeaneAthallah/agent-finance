import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import AnomalyController from '@/actions/App/Http/Controllers/AnomalyController';
import {
    AlertCircleIcon,
    CheckCircleIcon,
    ClockIcon,
    CpuIcon,
    ReviewIcon,
    SihandalIcon,
    TrendUpIcon,
} from '@/components/icons';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    PageHeader,
    ProgressBar,
    StatCard,
} from '@/components/ui';
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

function number(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
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

    const fiscalYears = Object.entries(documents.by_fiscal_year).sort(
        ([a], [b]) => (a < b ? -1 : 1),
    );
    const maxYearTotal = Math.max(1, ...fiscalYears.map(([, total]) => total));

    const topTypes = Object.entries(documents.by_type)
        .sort(([, a], [, b]) => b - a)
        .slice(0, 6);
    const maxTypeTotal = Math.max(1, ...topTypes.map(([, t]) => t));

    return (
        <AppShell
            title="Reports"
            subtitle={scope === 'opd' ? 'Scoped to your OPD' : 'Platform-wide'}
        >
            <Head title="Reports" />

            <PageHeader
                title="Reports"
                description={
                    scope === 'opd'
                        ? 'Platform reports scoped to your OPD. Platform staff see every OPD. Every figure is computed from real pipeline state.'
                        : 'Platform reports — every OPD, platform-wide. Every figure is computed from real pipeline state.'
                }
            />

            {/* Document KPIs */}
            <div className="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
                <StatCard
                    label="Total documents"
                    value={number(documents.total)}
                    variant="neutral"
                    icon={<TrendUpIcon size={15} />}
                />
                <StatCard
                    label="Review required"
                    value={number(documents.by_status.review_required ?? 0)}
                    hint="awaiting a human"
                    variant="amber"
                    icon={<ReviewIcon size={15} />}
                />
                <StatCard
                    label="Approved"
                    value={number(
                        (documents.by_status.approved ?? 0) +
                            (documents.by_status.completed ?? 0),
                    )}
                    hint="verified"
                    variant="green"
                    icon={<CheckCircleIcon size={15} />}
                />
                <StatCard
                    label="Failed"
                    value={number(
                        (documents.by_status.processing_failed ?? 0) +
                            (documents.by_status.ocr_failed ?? 0) +
                            (documents.by_status.ai_failed ?? 0) +
                            (documents.by_status.validation_failed ?? 0) +
                            (documents.by_status.sync_failed ?? 0),
                    )}
                    hint="need attention"
                    variant="red"
                    icon={<AlertCircleIcon size={15} />}
                />
            </div>

            {/* Volume charts */}
            <div className="mb-5 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Documents by fiscal year</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        {fiscalYears.length === 0 ? (
                            <p className="text-sm text-muted">
                                No documents yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {fiscalYears.map(([year, total]) => (
                                    <li key={year}>
                                        <div className="mb-1 flex items-center justify-between text-sm">
                                            <span className="font-medium text-foreground tabular-nums">
                                                {year}
                                            </span>
                                            <span className="text-muted tabular-nums">
                                                {number(total)}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-elevated">
                                            <div
                                                className="h-full rounded-full bg-brand transition-[width]"
                                                style={{
                                                    width: `${Math.round(
                                                        (total / maxYearTotal) *
                                                            100,
                                                    )}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            By document type (top {topTypes.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        {topTypes.length === 0 ? (
                            <p className="text-sm text-muted">
                                No documents yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {topTypes.map(([code, total]) => (
                                    <li key={code}>
                                        <div className="mb-1 flex items-center justify-between text-sm">
                                            <span className="font-medium tracking-wide text-foreground uppercase">
                                                {code}
                                            </span>
                                            <span className="text-muted tabular-nums">
                                                {number(total)}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-elevated">
                                            <div
                                                className="h-full rounded-full bg-info transition-[width]"
                                                style={{
                                                    width: `${Math.round(
                                                        (total / maxTypeTotal) *
                                                            100,
                                                    )}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Confidence + workload */}
            <div className="mb-5 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Extraction confidence</CardTitle>
                        <CardDescription>
                            Share of extracted fields by confidence bucket
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-4">
                        {confidenceTotal === 0 ? (
                            <p className="text-sm text-muted">
                                No extraction fields scored yet.
                            </p>
                        ) : (
                            <div>
                                {/* stacked distribution */}
                                <div
                                    className="mb-4 flex h-3 w-full overflow-hidden rounded-full"
                                    role="img"
                                    aria-label={`Confidence distribution: ${share(confidence.high)}% high, ${share(confidence.medium)}% medium, ${share(confidence.low)}% low`}
                                >
                                    <div
                                        className="h-full bg-success transition-[width]"
                                        style={{
                                            width: `${share(confidence.high)}%`,
                                        }}
                                    />
                                    <div
                                        className="h-full bg-warning transition-[width]"
                                        style={{
                                            width: `${share(confidence.medium)}%`,
                                        }}
                                    />
                                    <div
                                        className="h-full bg-danger transition-[width]"
                                        style={{
                                            width: `${share(confidence.low)}%`,
                                        }}
                                    />
                                </div>
                                <ul className="space-y-3">
                                    {[
                                        {
                                            label: 'High (≥ 85)',
                                            count: confidence.high,
                                            variant: 'success' as const,
                                        },
                                        {
                                            label: 'Medium (60–84)',
                                            count: confidence.medium,
                                            variant: 'warning' as const,
                                        },
                                        {
                                            label: 'Low (< 60)',
                                            count: confidence.low,
                                            variant: 'danger' as const,
                                        },
                                    ].map((bucket) => (
                                        <li
                                            key={bucket.label}
                                            className="flex items-center gap-3"
                                        >
                                            <span className="w-28 text-sm font-medium text-muted">
                                                {bucket.label}
                                            </span>
                                            <div className="flex-1">
                                                <ProgressBar
                                                    value={share(bucket.count)}
                                                    variant={bucket.variant}
                                                />
                                            </div>
                                            <span className="w-24 text-right text-sm text-muted tabular-nums">
                                                {number(bucket.count)} (
                                                {share(bucket.count)}%)
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Review workload</CardTitle>
                        <CardDescription>
                            What needs a human right now
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-2 gap-3">
                            <StatCard
                                label="Awaiting review"
                                value={number(workload.review_required)}
                                variant="amber"
                                icon={<ReviewIcon size={15} />}
                            />
                            <StatCard
                                label="Open anomalies"
                                value={number(workload.open_anomalies)}
                                icon={<AlertCircleIcon size={15} />}
                            />
                            <StatCard
                                label="Critical / high"
                                value={`${number(workload.critical_anomalies)} / ${number(workload.high_anomalies)}`}
                                variant="red"
                                icon={<AlertCircleIcon size={15} />}
                            />
                            <StatCard
                                label="SIHANDAL confirmations"
                                value={number(
                                    workload.pending_sihandal_confirmations,
                                )}
                                variant="purple"
                                icon={<SihandalIcon size={15} />}
                            />
                        </div>
                        <p className="mt-3 text-xs text-muted">
                            {number(workload.resolved_anomalies)} anomalies
                            resolved by reviewers.{' '}
                            <Link
                                className="font-medium text-brand hover:underline"
                                href={AnomalyController.index.url()}
                            >
                                Open the anomaly workspace
                            </Link>
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Sync health + AI cost */}
            <div className="mb-5 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>SIHANDAL sync health</CardTitle>
                        <CardDescription>
                            Failed syncs are retryable — SIHANDAL being
                            unavailable never loses a document.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="mb-3 flex h-3 w-full overflow-hidden rounded-full bg-elevated">
                            <div
                                className="h-full bg-warning transition-[width]"
                                style={{
                                    width: `${syncHealth.pending + syncHealth.success + syncHealth.failed === 0 ? 0 : Math.round((syncHealth.pending / Math.max(1, syncHealth.pending + syncHealth.success + syncHealth.failed)) * 100)}%`,
                                }}
                            />
                            <div
                                className="h-full bg-success transition-[width]"
                                style={{
                                    width: `${syncHealth.pending + syncHealth.success + syncHealth.failed === 0 ? 0 : Math.round((syncHealth.success / Math.max(1, syncHealth.pending + syncHealth.success + syncHealth.failed)) * 100)}%`,
                                }}
                            />
                            <div
                                className="h-full bg-danger transition-[width]"
                                style={{
                                    width: `${syncHealth.pending + syncHealth.success + syncHealth.failed === 0 ? 0 : Math.round((syncHealth.failed / Math.max(1, syncHealth.pending + syncHealth.success + syncHealth.failed)) * 100)}%`,
                                }}
                            />
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <StatCard
                                label="Pending"
                                value={number(syncHealth.pending)}
                                variant="amber"
                                icon={<ClockIcon size={15} />}
                            />
                            <StatCard
                                label="Succeeded"
                                value={number(syncHealth.success)}
                                variant="green"
                                icon={<CheckCircleIcon size={15} />}
                            />
                            <StatCard
                                label="Failed"
                                value={number(syncHealth.failed)}
                                variant="red"
                                icon={<AlertCircleIcon size={15} />}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>AI token &amp; cost</CardTitle>
                        <CardDescription>
                            Extraction runs across all documents
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-2 gap-3">
                            <StatCard
                                label="AI calls"
                                value={number(aiCost.calls)}
                                icon={<CpuIcon size={15} />}
                            />
                            <StatCard
                                label="Avg duration"
                                value={`${number(Math.round(aiCost.avg_duration_ms / 100) / 10)}s`}
                                icon={<ClockIcon size={15} />}
                            />
                            <StatCard
                                label="Input tokens"
                                value={number(aiCost.input_tokens)}
                            />
                            <StatCard
                                label="Output tokens"
                                value={number(aiCost.output_tokens)}
                            />
                        </div>
                        <div className="mt-3 flex items-center justify-between rounded-lg border border-border bg-elevated p-3">
                            <span className="text-xs font-medium text-muted">
                                Estimated cost
                            </span>
                            <span className="text-xl font-semibold text-foreground tabular-nums">
                                {aiCost.cost_estimate}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Cost by model */}
            <Card>
                <CardHeader>
                    <CardTitle>Cost by model</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    {aiCostByModel.length === 0 ? (
                        <p className="text-sm text-muted">
                            No AI usage recorded yet.
                        </p>
                    ) : (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-sunken/50 text-left text-[11px] font-medium tracking-wider text-subtle uppercase">
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
                                            className="border-b border-border transition-colors last:border-0 hover:bg-elevated"
                                        >
                                            <td className="py-2 pr-4 font-medium text-foreground">
                                                {row.model}
                                            </td>
                                            <td className="py-2 pr-4 text-muted tabular-nums">
                                                {number(row.calls)}
                                            </td>
                                            <td className="py-2 pr-4 text-muted tabular-nums">
                                                {number(row.input_tokens)}
                                            </td>
                                            <td className="py-2 pr-4 text-muted tabular-nums">
                                                {number(row.output_tokens)}
                                            </td>
                                            <td className="py-2 text-muted tabular-nums">
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
