import { Head, router, useForm } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Label,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type DocumentDetail = {
    id: number;
    status: string;
    original_filename: string;
    mime_type: string;
    file_size_human: string;
    page_count: number | null;
    fiscal_year: number | null;
    document_number: string | null;
    document_type?: { id: number; code: string; name: string } | null;
    opd?: { id: number; code: string; name: string } | null;
    uploader?: { id: number; name: string } | null;
    created_at: string;
};

type PipelineRun = {
    stage: string;
    status: string;
    error: string | null;
    duration_ms: number | null;
    version: number;
    created_at: string;
};

type PageState = {
    page_number: number;
    text_source: string | null;
    ocr_confidence: number | null;
    processing_status: string;
    error: string | null;
};

type Evidence = {
    page?: number;
    bbox?: number[];
    source_text?: string;
};

type ExtractionField = {
    field: string;
    value: string | null;
    confidence: number | null;
    evidence: Evidence | null;
};

type Extraction = {
    version: number;
    schema_name: string;
    schema_version: string;
    status: string;
    confidence: number | null;
    error: string | null;
    fields: ExtractionField[];
    ai_run?: {
        provider: string;
        model: string | null;
        prompt_name: string | null;
        prompt_version: string | null;
    } | null;
};

type Anomaly = {
    id: number;
    type: string;
    severity: string;
    status: string;
    title: string;
    detail: string | null;
    evidence: Record<string, unknown> | null;
};

type Validation = {
    id: number;
    version: number;
    engine_version: string;
    result: string;
    summary: { anomaly_count?: number; by_type?: Record<string, number> } | null;
    duration_ms: number | null;
    created_at: string;
    anomalies: Anomaly[];
};

type ReviewRecord = {
    id: number;
    decision: string;
    notes: string | null;
    reviewer?: { id: number; name: string } | null;
    created_at: string;
};

type Props = InertiaPageProps & {
    document: DocumentDetail;
    previewUrl: string;
    pipeline: PipelineRun[];
    pages: PageState[];
    extraction: Extraction | null;
    validation: Validation | null;
    reviews: ReviewRecord[];
    auth: { can: { review: boolean } };
};

const STAGE_LABELS: Record<string, string> = {
    pipeline: 'Pipeline started',
    text_extraction: 'Text extraction',
    ocr: 'OCR / page images',
    classification: 'AI classification',
    extraction: 'Financial extraction',
    validation: 'Validation',
    matching: 'SIHANDAL matching',
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

const DECISION_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red' | 'purple'
> = {
    approved: 'green',
    rejected: 'red',
    reanalysis_requested: 'amber',
};

const STAGE_ORDER = [
    'text_extraction',
    'ocr',
    'classification',
    'extraction',
    'validation',
    'matching',
];

function confidenceVariant(confidence: number | null): {
    variant: 'green' | 'amber' | 'red' | 'neutral';
    label: string;
} {
    if (confidence == null) {
        return { variant: 'neutral', label: 'n/a' };
    }

    if (confidence >= 85) {
        return { variant: 'green', label: `${confidence}%` };
    }

    if (confidence >= 60) {
        return { variant: 'amber', label: `${confidence}%` };
    }

    return { variant: 'red', label: `${confidence}%` };
}

const FIELD_LABELS: Record<string, string> = {
    document_number: 'Document number',
    document_date: 'Document date',
    fiscal_year: 'Fiscal year',
    opd_name: 'OPD name',
    amount: 'Amount',
    amount_in_words: 'Amount in words',
    payee_name: 'Payee / vendor',
    account_number: 'Account number',
    bank_name: 'Bank name',
    spm_kind: 'SPM kind',
    spp_number: 'SPP number',
    spm_number: 'SPM number',
    payment_method: 'Payment method',
    spp_kind: 'SPP kind',
    budget_account: 'Budget account',
    description: 'Description',
    reference_number: 'Reference number',
    total_budget: 'Total budget',
};

export default function DocumentShow({
    document,
    previewUrl,
    pipeline,
    pages,
    extraction,
    validation,
    reviews,
    auth,
}: Props) {
    const reviewForm = useForm<{ decision: string; notes: string }>({
        decision: '',
        notes: '',
    });

    function submitReview(decision: string) {
        reviewForm.clearErrors();
        reviewForm.setData('decision', decision);
        router.post(
            `/documents/${document.id}/review`,
            { decision, notes: reviewForm.data.notes },
            {
                preserveScroll: true,
                onSuccess: () => reviewForm.reset(),
            },
        );
    }

    return (
        <AppShell title="Document detail">
            <Head title={document.original_filename} />
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h2 className="text-base font-semibold text-neutral-900">
                        {document.original_filename}
                    </h2>
                    <Badge variant="blue">
                        {document.status.replaceAll('_', ' ')}
                    </Badge>
                </div>
                <a href={previewUrl} target="_blank" rel="noopener noreferrer">
                    <Button variant="outline" size="sm">
                        Open document
                    </Button>
                </a>
            </div>{' '}
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Preview</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div
                            id="document-page-1"
                            className="flex h-64 items-center justify-center rounded border border-dashed border-neutral-300 bg-neutral-50 text-sm text-neutral-500 transition-shadow"
                        >
                            Inline page viewer (page images + bbox highlights)
                            lands with the review workspace. Use “Open document”
                            for the full preview.
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Metadata</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <Row
                            label="Type"
                            value={
                                document.document_type?.name ?? 'Unclassified'
                            }
                        />
                        <Row label="OPD" value={document.opd?.name ?? '—'} />
                        <Row
                            label="Fiscal year"
                            value={document.fiscal_year?.toString() ?? '—'}
                        />
                        <Row
                            label="Document number"
                            value={document.document_number ?? '—'}
                        />
                        <Row
                            label="Pages"
                            value={document.page_count?.toString() ?? '—'}
                        />
                        <Row label="Size" value={document.file_size_human} />
                        <Row
                            label="Uploaded by"
                            value={document.uploader?.name ?? '—'}
                        />
                        <Row
                            label="Uploaded at"
                            value={new Date(
                                document.created_at,
                            ).toLocaleString()}
                        />
                    </CardContent>
                </Card>
            </div>{' '}
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Processing pipeline</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <ol className="space-y-2">
                            {STAGE_ORDER.map((stage) => {
                                const run = [...pipeline]
                                    .reverse()
                                    .find((r) => r.stage === stage);

                                const state = !run
                                    ? 'pending'
                                    : run.status === 'completed'
                                      ? 'done'
                                      : run.status === 'failed'
                                        ? 'failed'
                                        : 'running';

                                return (
                                    <li
                                        key={stage}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <span className="flex items-center gap-2">
                                            <span
                                                className={
                                                    state === 'done'
                                                        ? 'text-green-600'
                                                        : state === 'failed'
                                                          ? 'text-red-600'
                                                          : state === 'running'
                                                            ? 'animate-pulse text-blue-600'
                                                            : 'text-neutral-300'
                                                }
                                            >
                                                {state === 'done'
                                                    ? '✓'
                                                    : state === 'failed'
                                                      ? '✕'
                                                      : state === 'running'
                                                        ? '●'
                                                        : '○'}
                                            </span>
                                            <span
                                                className={
                                                    state === 'pending'
                                                        ? 'text-neutral-400'
                                                        : 'text-neutral-800'
                                                }
                                            >
                                                {STAGE_LABELS[stage] ?? stage}
                                            </span>
                                        </span>
                                        {run?.error ? (
                                            <span
                                                className="max-w-[50%] truncate text-xs text-red-600"
                                                title={run.error}
                                            >
                                                {run.error}
                                            </span>
                                        ) : run && run.duration_ms != null ? (
                                            <span className="text-xs text-neutral-400 tabular-nums">
                                                {(
                                                    run.duration_ms / 1000
                                                ).toFixed(1)}
                                                s
                                            </span>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ol>
                        {pipeline.some((r) => r.status === 'failed') ? (
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-3"
                                onClick={() =>
                                    router.post(
                                        `/documents/${document.id}/process`,
                                    )
                                }
                            >
                                Retry processing
                            </Button>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Pages</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        {pages.length === 0 ? (
                            <p className="text-sm text-neutral-500">
                                Not processed yet.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                        <th className="py-1.5 pr-3 font-medium">
                                            Page
                                        </th>
                                        <th className="py-1.5 pr-3 font-medium">
                                            Source
                                        </th>
                                        <th className="py-1.5 font-medium">
                                            Confidence
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pages.map((page) => (
                                        <tr
                                            key={page.page_number}
                                            className="border-b border-neutral-100"
                                        >
                                            <td className="py-1.5 pr-3 tabular-nums">
                                                {page.page_number}
                                            </td>
                                            <td className="py-1.5 pr-3">
                                                {page.text_source ?? '—'}
                                            </td>
                                            <td className="py-1.5 tabular-nums">
                                                {page.ocr_confidence != null
                                                    ? `${page.ocr_confidence}%`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>AI Extraction</CardTitle>
                        {extraction ? (
                            <p className="mt-0.5 text-xs text-neutral-500">
                                {extraction.schema_name} schema v
                                {extraction.schema_version} · run v
                                {extraction.version}
                                {extraction.ai_run?.model
                                    ? ` · ${extraction.ai_run.provider}/${extraction.ai_run.model}`
                                    : ''}
                            </p>
                        ) : null}
                    </CardHeader>
                    <CardContent className="p-4">
                        {extraction === null ? (
                            <p className="text-sm text-neutral-500">
                                No extraction yet — it runs automatically after
                                classification.
                            </p>
                        ) : extraction.status === 'failed' ? (
                            <div className="text-sm">
                                <Badge variant="red">Extraction failed</Badge>
                                {extraction.error ? (
                                    <p className="mt-2 text-xs text-red-600">
                                        {extraction.error}
                                    </p>
                                ) : null}
                            </div>
                        ) : extraction.status === 'running' ? (
                            <p className="animate-pulse text-sm text-blue-600">
                                Extracting financial data…
                            </p>
                        ) : (
                            <div>
                                <div className="mb-3 flex items-center gap-2">
                                    <span className="text-xs text-neutral-500">
                                        Overall confidence
                                    </span>
                                    <Badge
                                        variant={
                                            confidenceVariant(
                                                extraction.confidence,
                                            ).variant
                                        }
                                    >
                                        {
                                            confidenceVariant(
                                                extraction.confidence,
                                            ).label
                                        }
                                    </Badge>
                                </div>
                                {extraction.fields.length === 0 ? (
                                    <p className="text-sm text-neutral-500">
                                        No fields extracted.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-neutral-100">
                                        {extraction.fields.map((field) => {
                                            const badge = confidenceVariant(
                                                field.confidence,
                                            );
                                            const hasEvidence =
                                                field.evidence?.page != null ||
                                                field.evidence?.source_text;

                                            return (
                                                <li
                                                    key={field.field}
                                                    className="py-2 first:pt-0 last:pb-0"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-xs text-neutral-500">
                                                            {FIELD_LABELS[
                                                                field.field
                                                            ] ??
                                                                field.field.replaceAll(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                        </span>
                                                        <Badge
                                                            variant={
                                                                badge.variant
                                                            }
                                                        >
                                                            {badge.label}
                                                        </Badge>
                                                    </div>
                                                    <p
                                                        className={
                                                            field.value
                                                                ? 'mt-0.5 text-sm font-medium text-neutral-900'
                                                                : 'mt-0.5 text-sm text-neutral-400'
                                                        }
                                                    >
                                                        {field.value ||
                                                            'not found'}
                                                    </p>
                                                    {hasEvidence ? (
                                                        <button
                                                            type="button"
                                                            className="mt-1 text-left text-xs text-blue-700 hover:underline"
                                                            title={
                                                                field.evidence
                                                                    ?.source_text ??
                                                                undefined
                                                            }
                                                            onClick={() =>
                                                                jumpToEvidence(
                                                                    field
                                                                        .evidence
                                                                        ?.page,
                                                                )
                                                            }
                                                        >
                                                            {field.evidence
                                                                ?.page != null
                                                                ? `Show on page ${field.evidence.page}`
                                                                : 'Show source'}
                                                        </button>
                                                    ) : null}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Validation</CardTitle>
                        {validation ? (
                            <p className="mt-0.5 text-xs text-neutral-500">
                                {validation.result === 'passed'
                                    ? 'All checks passed'
                                    : `${validation.summary?.anomaly_count ?? 0} finding(s)`}{' '}
                                · engine v{validation.engine_version} · run v
                                {validation.version}
                            </p>
                        ) : null}
                    </CardHeader>
                    <CardContent className="p-4">
                        {validation === null ? (
                            <p className="text-sm text-neutral-500">
                                {extraction?.status === 'completed'
                                    ? 'Validation queued…'
                                    : 'Runs automatically after extraction.'}
                            </p>
                        ) : (
                            <div>
                                <div className="mb-3 flex items-center gap-2">
                                    <Badge
                                        variant={
                                            validation.result === 'passed'
                                                ? 'green'
                                                : 'amber'
                                        }
                                    >
                                        {validation.result}
                                    </Badge>
                                    {validation.duration_ms != null ? (
                                        <span className="text-xs text-neutral-400 tabular-nums">
                                            {(
                                                validation.duration_ms / 1000
                                            ).toFixed(1)}
                                            s
                                        </span>
                                    ) : null}
                                </div>
                                {validation.anomalies.length === 0 ? (
                                    <p className="text-sm text-neutral-500">
                                        No anomalies detected.
                                    </p>
                                ) : (
                                    <ul className="space-y-2">
                                        {validation.anomalies.map((anomaly) => (
                                            <li
                                                key={anomaly.id}
                                                className="rounded-md border border-neutral-200 p-2.5"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-sm font-medium text-neutral-900">
                                                        {anomaly.title}
                                                    </span>
                                                    <span className="flex items-center gap-1.5">
                                                        <Badge
                                                            variant={
                                                                SEVERITY_VARIANTS[
                                                                    anomaly
                                                                        .severity
                                                                ] ?? 'neutral'
                                                            }
                                                        >
                                                            {
                                                                anomaly.severity
                                                            }
                                                        </Badge>
                                                        <Badge
                                                            variant={
                                                                anomaly.status ===
                                                                'open'
                                                                    ? 'amber'
                                                                    : 'green'
                                                            }
                                                        >
                                                            {anomaly.status}
                                                        </Badge>
                                                    </span>
                                                </div>
                                                {anomaly.detail ? (
                                                    <p className="mt-1 text-xs text-neutral-600">
                                                        {anomaly.detail}
                                                    </p>
                                                ) : null}
                                                <p className="mt-1 text-[11px] tracking-wide text-neutral-400 uppercase">
                                                    {
                                                        anomaly.type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )
                                                    }
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Review</CardTitle>
                        <p className="mt-0.5 text-xs text-neutral-500">
                            {reviews.length} decision
                            {reviews.length === 1 ? '' : 's'} recorded
                        </p>
                    </CardHeader>
                    <CardContent className="p-4">
                        {reviews.length > 0 ? (
                            <ul className="mb-4 space-y-2">
                                {reviews.map((review) => (
                                    <li
                                        key={review.id}
                                        className="rounded-md border border-neutral-200 p-2.5 text-sm"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <Badge
                                                variant={
                                                    DECISION_VARIANTS[
                                                        review.decision
                                                    ] ?? 'neutral'
                                                }
                                            >
                                                {review.decision.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                            <span className="text-xs text-neutral-400">
                                                {new Date(
                                                    review.created_at,
                                                ).toLocaleString()}
                                            </span>
                                        </div>
                                        {review.reviewer?.name ? (
                                            <p className="mt-1 text-xs text-neutral-500">
                                                by {review.reviewer.name}
                                            </p>
                                        ) : null}
                                        {review.notes ? (
                                            <p className="mt-1 text-xs text-neutral-700">
                                                {review.notes}
                                            </p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                        {auth.can.review ? (
                            <div className="space-y-3">
                                <div>
                                    <Label htmlFor="review-notes">
                                        Review notes
                                    </Label>
                                    <textarea
                                        id="review-notes"
                                        className="min-h-16 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                                        placeholder="Why this decision? (optional)"
                                        value={reviewForm.data.notes}
                                        onChange={(e) =>
                                            reviewForm.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        disabled={reviewForm.processing}
                                        onClick={() => submitReview('approved')}
                                    >
                                        Approve
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="danger"
                                        disabled={reviewForm.processing}
                                        onClick={() => submitReview('rejected')}
                                    >
                                        Reject
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={reviewForm.processing}
                                        onClick={() =>
                                            submitReview(
                                                'reanalysis_requested',
                                            )
                                        }
                                    >
                                        Request re-analysis
                                    </Button>
                                </div>
                                {reviewForm.errors.decision ? (
                                    <p className="text-xs text-red-600">
                                        {reviewForm.errors.decision}
                                    </p>
                                ) : null}
                            </div>
                        ) : (
                            <p className="text-sm text-neutral-500">
                                Verifier role required to review.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppShell>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-neutral-100 pb-1.5 last:border-0">
            <span className="text-xs text-neutral-500">{label}</span>
            <span className="text-right font-medium text-neutral-900">
                {value}
            </span>
        </div>
    );
}

/**
 * Jump to the evidence location (spec §12): scrolls the preview pane and
 * highlights the target page. The full page-image viewer with bbox overlays
 * lands with the viewer work; until then the preview link + anchor gives the
 * reviewer a direct path to the cited page.
 */
function jumpToEvidence(page?: number) {
    if (page == null) {
        return;
    }

    const target = document.getElementById(`document-page-${page}`);

    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('ring-2', 'ring-blue-500');
        window.setTimeout(
            () => target.classList.remove('ring-2', 'ring-blue-500'),
            2000,
        );
    }
}
