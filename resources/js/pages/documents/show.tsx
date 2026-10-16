import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import ReviewController from '@/actions/App/Http/Controllers/ReviewController';
import SihandalMatchController from '@/actions/App/Http/Controllers/SihandalMatchController';
import {
    AlertCircleIcon,
    AiIcon,
    BuildingIcon,
    CheckCircleIcon,
    ClockIcon,
    DownloadIcon,
    EyeIcon,
    InfoIcon,
    RefreshIcon,
    ScanIcon,
} from '@/components/icons';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    ConfidenceBar,
    FieldRow,
    Label,
    PageHeader,
    SectionTitle,
    StatusBadge,
    Textarea,
    confidenceColor,
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
    summary: {
        anomaly_count?: number;
        by_type?: Record<string, number>;
    } | null;
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

type SihandalMatch = {
    id: number;
    version: number;
    entity_type: string;
    entity_id: string;
    sihandal_data: Record<string, string | number | null>;
    confidence: number;
    status: string;
    confirmed_by?: { id: number; name: string } | null;
    confirmed_at: string | null;
    created_at: string;
};

type SihandalSync = {
    id: number;
    sihandal_match_id: number | null;
    user?: { id: number; name: string } | null;
    endpoint: string;
    method: string;
    request: Record<string, unknown>;
    response: Record<string, unknown> | null;
    http_status: number | null;
    status: string;
    error: string | null;
    attempts: number;
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
    sihandal: { matches: SihandalMatch[]; syncs: SihandalSync[] };
    can: { review: boolean };
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

const STAGE_ORDER = [
    'text_extraction',
    'ocr',
    'classification',
    'extraction',
    'validation',
    'matching',
];

const SEVERITY_VARIANTS: Record<string, 'neutral' | 'blue' | 'amber' | 'red'> =
    {
        info: 'blue',
        warning: 'amber',
        high: 'red',
        critical: 'red',
    };

const DECISION_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red'
> = {
    approved: 'green',
    rejected: 'red',
    reanalysis_requested: 'amber',
};

const MATCH_STATUS_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red'
> = {
    pending: 'amber',
    confirmed: 'green',
    rejected: 'red',
};

const SYNC_STATUS_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red'
> = {
    pending: 'amber',
    success: 'green',
    failed: 'red',
};

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

function fieldLabel(field: string): string {
    return FIELD_LABELS[field] ?? field.replaceAll('_', ' ');
}

function formatRupiah(value: string | number): string {
    const numeric =
        typeof value === 'string' ? Number.parseFloat(value) : value;

    return Number.isFinite(numeric)
        ? `Rp ${numeric.toLocaleString('id-ID')}`
        : String(value);
}

function confidenceLabel(confidence: number | null): string {
    if (confidence == null) {
        return 'n/a';
    }

    return `${confidence}%`;
}

export default function DocumentShow({
    document,
    previewUrl,
    pipeline,
    pages,
    extraction,
    validation,
    reviews,
    sihandal,
    can,
}: Props) {
    const reviewForm = useForm<{ decision: string; notes: string }>({
        decision: '',
        notes: '',
    });

    const [syncBusy, setSyncBusy] = useState(false);

    function submitReview(decision: string) {
        reviewForm.clearErrors();
        reviewForm.setData('decision', decision);

        router.post(
            ReviewController.store({ document: document.id }).url,
            { decision, notes: reviewForm.data.notes },
            {
                preserveScroll: true,
                onSuccess: () => reviewForm.reset(),
            },
        );
    }

    function confirmMatch(matchId: number) {
        setSyncBusy(true);

        router.post(
            SihandalMatchController.confirm({
                document: document.id,
                match: matchId,
            }).url,
            {},
            { preserveScroll: true, onFinish: () => setSyncBusy(false) },
        );
    }

    function rejectMatch(matchId: number) {
        setSyncBusy(true);

        router.post(
            SihandalMatchController.reject({
                document: document.id,
                match: matchId,
            }).url,
            {},
            { preserveScroll: true, onFinish: () => setSyncBusy(false) },
        );
    }

    function retrySync(syncId: number) {
        setSyncBusy(true);

        router.post(
            SihandalMatchController.retry({
                document: document.id,
                sync: syncId,
            }).url,
            {},
            { preserveScroll: true, onFinish: () => setSyncBusy(false) },
        );
    }

    function retryProcessing() {
        router.post(
            DocumentController.process({ document: document.id }).url,
            {},
            { preserveScroll: true },
        );
    }

    const failedStage = pipeline.some((run) => run.status === 'failed');

    // AI findings, bucketed by confidence so the reviewer always
    // knows what to trust and what to verify.
    const findings =
        extraction?.status === 'completed'
            ? bucketFindings(extraction.fields)
            : null;

    return (
        <AppShell
            title="Document detail"
            subtitle={document.document_type?.name}
        >
            <Head title={document.original_filename} />

            <PageHeader
                title={document.original_filename}
                description={`${document.document_number ?? 'No document number'} · ${document.file_size_human}${document.page_count ? ` · ${document.page_count} pages` : ''}`}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={retryProcessing}
                            disabled={!failedStage}
                        >
                            <RefreshIcon size={15} />
                            Reprocess
                        </Button>
                        <a
                            href={previewUrl}
                            download
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button variant="outline">
                                <DownloadIcon size={15} />
                                Download
                            </Button>
                        </a>
                        <a
                            href={previewUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Button>
                                <EyeIcon size={15} />
                                Open document
                            </Button>
                        </a>
                    </>
                }
            />

            <div className="mb-5 flex flex-wrap items-center gap-2">
                <StatusBadge status={document.status} />
                <Badge variant="neutral">
                    {document.document_type?.name ?? 'Unclassified'}
                </Badge>
                <Badge variant="neutral">
                    <BuildingIcon size={12} />
                    {document.opd?.name ?? 'No OPD'}
                </Badge>
                <Badge variant="neutral">
                    TA {document.fiscal_year ?? '—'}
                </Badge>
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-3">
                {/* Main column */}
                <div className="space-y-4 lg:col-span-2">
                    {/* Preview */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Document preview</CardTitle>
                            <CardDescription>
                                Page-by-page OCR state — select a page to
                                inspect its extraction source
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {pages.length === 0 ? (
                                <div className="flex h-48 items-center justify-center rounded-md border border-dashed border-border-strong bg-elevated text-sm text-muted">
                                    Pages appear once processing completes.
                                </div>
                            ) : (
                                <ul className="grid gap-2 sm:grid-cols-2">
                                    {pages.map((page) => (
                                        <li
                                            key={page.page_number}
                                            id={`document-page-${page.page_number}`}
                                            className="rounded-lg border border-border bg-elevated p-3"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="flex items-center gap-2 text-sm font-medium text-foreground">
                                                    <ScanIcon
                                                        size={14}
                                                        className="text-muted"
                                                    />
                                                    Page {page.page_number}
                                                </span>
                                                <span className="text-xs text-subtle tabular-nums">
                                                    {page.ocr_confidence != null
                                                        ? `${page.ocr_confidence}% OCR`
                                                        : 'no score'}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs text-muted">
                                                Source:{' '}
                                                {page.text_source ?? '—'}
                                                {' · '}
                                                {page.processing_status}
                                            </p>
                                            {page.error ? (
                                                <p
                                                    className="mt-1 truncate text-xs text-danger"
                                                    title={page.error}
                                                >
                                                    {page.error}
                                                </p>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    {/* AI Analysis workspace */}
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <CardTitle>AI analysis</CardTitle>
                                {extraction?.ai_run?.model ? (
                                    <Badge variant="purple">
                                        <AiIcon size={12} />
                                        {extraction.ai_run.provider}
                                        {extraction.ai_run.model
                                            ? ` · ${extraction.ai_run.model}`
                                            : ''}
                                    </Badge>
                                ) : null}
                            </div>
                            {extraction ? (
                                <CardDescription>
                                    {extraction.schema_name} schema v
                                    {extraction.schema_version} · run v
                                    {extraction.version}
                                    {extraction.ai_run?.prompt_version
                                        ? ` · prompt v${extraction.ai_run.prompt_version}`
                                        : ''}
                                </CardDescription>
                            ) : null}
                        </CardHeader>
                        <CardContent className="p-4">
                            {extraction === null ? (
                                <p className="flex items-center gap-2 text-sm text-muted">
                                    <ClockIcon size={15} />
                                    No extraction yet — it runs automatically
                                    after classification.
                                </p>
                            ) : extraction.status === 'failed' ? (
                                <div className="text-sm">
                                    <Badge variant="red">
                                        <AlertCircleIcon size={12} />
                                        Extraction failed
                                    </Badge>
                                    {extraction.error ? (
                                        <p className="mt-2 text-xs text-danger">
                                            {extraction.error}
                                        </p>
                                    ) : null}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="mt-3"
                                        onClick={retryProcessing}
                                    >
                                        <RefreshIcon size={14} />
                                        Retry processing
                                    </Button>
                                </div>
                            ) : extraction.status === 'running' ? (
                                <p className="flex items-center gap-2 text-sm text-info">
                                    <RefreshIcon
                                        size={15}
                                        className="animate-spin"
                                    />
                                    Extracting financial data…
                                </p>
                            ) : findings ? (
                                <div>
                                    {/* Overall confidence */}
                                    <div className="mb-5 rounded-lg border border-border bg-elevated p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-xs font-medium text-muted">
                                                Overall AI confidence
                                            </span>
                                            <span
                                                className={`text-sm font-semibold tabular-nums ${
                                                    extraction.confidence ==
                                                    null
                                                        ? 'text-subtle'
                                                        : confidenceColor(
                                                                extraction.confidence,
                                                            ) === 'success'
                                                          ? 'text-success'
                                                          : confidenceColor(
                                                                  extraction.confidence,
                                                              ) === 'warning'
                                                            ? 'text-warning'
                                                            : 'text-danger'
                                                }`}
                                            >
                                                {confidenceLabel(
                                                    extraction.confidence,
                                                )}
                                            </span>
                                        </div>
                                        <ConfidenceBar
                                            value={extraction.confidence}
                                            className="mt-2"
                                        />
                                        <p className="mt-1.5 text-xs text-subtle">
                                            AI output is a recommendation —
                                            verify the fields below before
                                            approving.
                                        </p>
                                    </div>

                                    {extraction.fields.length === 0 ? (
                                        <p className="text-sm text-muted">
                                            No fields extracted.
                                        </p>
                                    ) : (
                                        <div className="space-y-5">
                                            <FindingGroup
                                                title="Confirmed by AI"
                                                description="High confidence — still verify before approving"
                                                variant="green"
                                                icon={
                                                    <CheckCircleIcon
                                                        size={14}
                                                    />
                                                }
                                                fields={findings.confirmed}
                                                onEvidence={jumpToEvidence}
                                            />
                                            <FindingGroup
                                                title="Needs review"
                                                description="Medium confidence — a human must verify these"
                                                variant="amber"
                                                icon={<ClockIcon size={14} />}
                                                fields={findings.needsReview}
                                                onEvidence={jumpToEvidence}
                                            />
                                            <FindingGroup
                                                title="Low confidence"
                                                description="The AI is unsure — treat as unverified"
                                                variant="red"
                                                icon={
                                                    <AlertCircleIcon
                                                        size={14}
                                                    />
                                                }
                                                fields={findings.low}
                                                onEvidence={jumpToEvidence}
                                            />
                                        </div>
                                    )}
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    {/* Validation */}
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <CardTitle>Validation</CardTitle>
                                {validation ? (
                                    <Badge
                                        variant={
                                            validation.result === 'passed'
                                                ? 'green'
                                                : 'amber'
                                        }
                                    >
                                        {validation.result === 'passed' ? (
                                            <CheckCircleIcon size={12} />
                                        ) : (
                                            <AlertCircleIcon size={12} />
                                        )}
                                        {validation.result === 'passed'
                                            ? 'All checks passed'
                                            : `${validation.summary?.anomaly_count ?? 0} finding${
                                                  (validation.summary
                                                      ?.anomaly_count ?? 0) ===
                                                  1
                                                      ? ''
                                                      : 's'
                                              }`}
                                    </Badge>
                                ) : null}
                            </div>
                            {validation ? (
                                <CardDescription>
                                    Engine v{validation.engine_version} · run v
                                    {validation.version}
                                    {validation.duration_ms != null
                                        ? ` · ${(validation.duration_ms / 1000).toFixed(1)}s`
                                        : ''}
                                </CardDescription>
                            ) : null}
                        </CardHeader>
                        <CardContent className="p-4">
                            {validation === null ? (
                                <p className="text-sm text-muted">
                                    {extraction?.status === 'completed'
                                        ? 'Validation queued…'
                                        : 'Runs automatically after extraction.'}
                                </p>
                            ) : validation.anomalies.length === 0 ? (
                                <p className="flex items-center gap-2 text-sm text-success">
                                    <CheckCircleIcon size={15} />
                                    No anomalies detected — every rule passed.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {validation.anomalies.map((anomaly) => (
                                        <li
                                            key={anomaly.id}
                                            className="rounded-lg border border-border p-3"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="text-sm font-medium text-foreground">
                                                    {anomaly.title}
                                                </span>
                                                <span className="flex items-center gap-1.5">
                                                    <Badge
                                                        variant={
                                                            SEVERITY_VARIANTS[
                                                                anomaly.severity
                                                            ] ?? 'neutral'
                                                        }
                                                    >
                                                        {anomaly.severity}
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
                                                <p className="mt-1 text-xs text-muted">
                                                    {anomaly.detail}
                                                </p>
                                            ) : null}
                                            <p className="mt-1.5 text-[11px] font-medium tracking-wide text-subtle uppercase">
                                                {anomaly.type.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Side column */}
                <div className="space-y-4">
                    {/* Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 py-2">
                            <FieldRow
                                label="Type"
                                value={
                                    document.document_type?.name ??
                                    'Unclassified'
                                }
                            />
                            <FieldRow
                                label="OPD"
                                value={document.opd?.name ?? '—'}
                            />
                            <FieldRow
                                label="Fiscal year"
                                value={document.fiscal_year?.toString() ?? '—'}
                            />
                            <FieldRow
                                label="Document number"
                                value={document.document_number ?? '—'}
                            />
                            <FieldRow
                                label="Pages"
                                value={document.page_count?.toString() ?? '—'}
                            />
                            <FieldRow
                                label="Size"
                                value={document.file_size_human}
                            />
                            <FieldRow
                                label="Uploaded by"
                                value={document.uploader?.name ?? '—'}
                            />
                            <FieldRow
                                label="Uploaded at"
                                value={new Date(
                                    document.created_at,
                                ).toLocaleString('id-ID')}
                            />
                        </CardContent>
                    </Card>

                    {/* Pipeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Processing pipeline</CardTitle>
                        </CardHeader>
                        <CardContent className="p-4">
                            <ol className="space-y-2.5">
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
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span className="flex min-w-0 items-center gap-2.5">
                                                <span
                                                    className={
                                                        state === 'done'
                                                            ? 'text-success'
                                                            : state === 'failed'
                                                              ? 'text-danger'
                                                              : state ===
                                                                  'running'
                                                                ? 'animate-pulse text-info'
                                                                : 'text-border-strong'
                                                    }
                                                >
                                                    {state === 'done' ? (
                                                        <CheckCircleIcon
                                                            size={15}
                                                        />
                                                    ) : state === 'failed' ? (
                                                        <AlertCircleIcon
                                                            size={15}
                                                        />
                                                    ) : state === 'running' ? (
                                                        <RefreshIcon
                                                            size={15}
                                                            className="animate-spin"
                                                        />
                                                    ) : (
                                                        <ClockIcon size={15} />
                                                    )}
                                                </span>
                                                <span
                                                    className={
                                                        state === 'pending'
                                                            ? 'text-subtle'
                                                            : 'text-foreground'
                                                    }
                                                >
                                                    {STAGE_LABELS[stage] ??
                                                        stage}
                                                </span>
                                            </span>
                                            {run?.error ? (
                                                <span
                                                    className="max-w-[45%] truncate text-xs text-danger"
                                                    title={run.error}
                                                >
                                                    {run.error}
                                                </span>
                                            ) : run &&
                                              run.duration_ms != null ? (
                                                <span className="text-xs text-subtle tabular-nums">
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
                            {failedStage ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    onClick={retryProcessing}
                                >
                                    <RefreshIcon size={14} />
                                    Retry processing
                                </Button>
                            ) : null}
                        </CardContent>
                    </Card>

                    {/* Review */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Human review</CardTitle>
                            <CardDescription>
                                {reviews.length} decision
                                {reviews.length === 1 ? '' : 's'} recorded
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-4">
                            {reviews.length > 0 ? (
                                <ul className="mb-4 space-y-2">
                                    {reviews.map((review) => (
                                        <li
                                            key={review.id}
                                            className="rounded-lg border border-border p-2.5 text-sm"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
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
                                                <span className="text-xs text-subtle tabular-nums">
                                                    {new Date(
                                                        review.created_at,
                                                    ).toLocaleString('id-ID')}
                                                </span>
                                            </div>
                                            {review.reviewer?.name ? (
                                                <p className="mt-1 text-xs text-muted">
                                                    by {review.reviewer.name}
                                                </p>
                                            ) : null}
                                            {review.notes ? (
                                                <p className="mt-1 text-xs text-foreground">
                                                    {review.notes}
                                                </p>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            ) : null}

                            {can.review ? (
                                <form
                                    onSubmit={(event: FormEvent) => {
                                        event.preventDefault();
                                    }}
                                    className="space-y-3"
                                >
                                    <div>
                                        <Label htmlFor="review-notes">
                                            Review notes
                                        </Label>
                                        <Textarea
                                            id="review-notes"
                                            rows={3}
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
                                            onClick={() =>
                                                submitReview('approved')
                                            }
                                        >
                                            <CheckCircleIcon size={14} />
                                            Approve
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="danger"
                                            disabled={reviewForm.processing}
                                            onClick={() =>
                                                submitReview('rejected')
                                            }
                                        >
                                            <AlertCircleIcon size={14} />
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
                                            <RefreshIcon size={14} />
                                            Re-analyze
                                        </Button>
                                    </div>
                                    {reviewForm.errors.decision ? (
                                        <p className="text-xs text-danger">
                                            {reviewForm.errors.decision}
                                        </p>
                                    ) : null}
                                </form>
                            ) : (
                                <p className="flex items-start gap-2 text-sm text-muted">
                                    <InfoIcon
                                        size={15}
                                        className="mt-0.5 shrink-0"
                                    />
                                    Verifier role required to review.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* SIHANDAL integration */}
            <Card className="mt-4">
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle>SIHANDAL matching</CardTitle>
                        <CardDescription className="mt-0">
                            Match candidates from the system of record,
                            confidence-ordered. Confirming a candidate on an
                            approved document syncs it through the API — the
                            only write path to SIHANDAL.
                        </CardDescription>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6 p-4">
                    <section>
                        <SectionTitle
                            title="Match candidates"
                            description="AI-extracted data matched against SIHANDAL records"
                        />
                        {sihandal.matches.length === 0 ? (
                            <p className="flex items-center gap-2 rounded-md border border-dashed border-border-strong bg-elevated px-4 py-6 text-sm text-muted">
                                <InfoIcon size={15} />
                                No SIHANDAL match candidates yet. Candidates
                                appear once validation completes and SIHANDAL
                                returns possible matches for the extracted data.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {sihandal.matches.map((match) => (
                                    <li
                                        key={match.id}
                                        className="rounded-lg border border-border p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                <Badge
                                                    variant={
                                                        MATCH_STATUS_VARIANTS[
                                                            match.status
                                                        ] ?? 'neutral'
                                                    }
                                                >
                                                    {match.confidence}% match
                                                </Badge>
                                                <span className="truncate font-medium text-foreground">
                                                    {String(
                                                        match.sihandal_data
                                                            .uraian ??
                                                            match.entity_type,
                                                    )}
                                                </span>
                                                <span className="text-xs text-muted">
                                                    {String(
                                                        match.sihandal_data
                                                            .rekening ??
                                                            match.entity_id,
                                                    )}
                                                    {match.sihandal_data
                                                        .amount != null
                                                        ? ` · ${formatRupiah(
                                                              match
                                                                  .sihandal_data
                                                                  .amount as
                                                                  | string
                                                                  | number,
                                                          )}`
                                                        : ''}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {can.review &&
                                                document.status ===
                                                    'approved' &&
                                                match.status === 'pending' ? (
                                                    <div className="flex gap-1.5">
                                                        <Button
                                                            size="sm"
                                                            disabled={syncBusy}
                                                            onClick={() =>
                                                                confirmMatch(
                                                                    match.id,
                                                                )
                                                            }
                                                        >
                                                            <CheckCircleIcon
                                                                size={14}
                                                            />
                                                            Confirm &amp; sync
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={syncBusy}
                                                            onClick={() =>
                                                                rejectMatch(
                                                                    match.id,
                                                                )
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Badge
                                                        variant={
                                                            MATCH_STATUS_VARIANTS[
                                                                match.status
                                                            ] ?? 'neutral'
                                                        }
                                                    >
                                                        {match.status}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <p className="mt-1.5 text-xs text-subtle">
                                            v{match.version} · entity{' '}
                                            {match.entity_type}/
                                            {match.entity_id}
                                            {match.confirmed_by?.name
                                                ? ` · ${
                                                      match.status ===
                                                      'rejected'
                                                          ? 'rejected'
                                                          : 'confirmed'
                                                  } by ${
                                                      match.confirmed_by.name
                                                  }${
                                                      match.confirmed_at
                                                          ? ` on ${new Date(
                                                                match.confirmed_at,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}`
                                                          : ''
                                                  }`
                                                : ''}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section>
                        <SectionTitle
                            title="Sync history"
                            description="Every write to SIHANDAL, with its outcome"
                        />
                        {sihandal.syncs.length === 0 ? (
                            <p className="text-sm text-muted">
                                No syncs yet — confirmed candidates on approved
                                documents are pushed to SIHANDAL and recorded
                                here.
                            </p>
                        ) : (
                            <div className="scroll-thin overflow-x-auto">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead>
                                        <tr className="border-b border-border bg-sunken/50 text-left text-[11px] font-medium tracking-wider text-subtle uppercase">
                                            <th className="py-2 pr-4 font-medium">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Request
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
                                            <th className="py-2 font-medium">
                                                When
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sihandal.syncs.map((sync) => (
                                            <tr
                                                key={sync.id}
                                                className="border-b border-border transition-colors last:border-0 hover:bg-elevated"
                                            >
                                                <td className="py-2 pr-4">
                                                    <Badge
                                                        variant={
                                                            SYNC_STATUS_VARIANTS[
                                                                sync.status
                                                            ] ?? 'neutral'
                                                        }
                                                    >
                                                        {sync.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-2 pr-4">
                                                    <span className="font-mono text-xs text-foreground">
                                                        {sync.method}{' '}
                                                        {sync.endpoint}
                                                    </span>
                                                    {sync.error ? (
                                                        <p
                                                            className="mt-0.5 truncate text-xs text-danger"
                                                            title={sync.error}
                                                        >
                                                            {sync.error}
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="hidden py-2 pr-4 text-muted tabular-nums sm:table-cell">
                                                    {sync.http_status ?? '—'}
                                                </td>
                                                <td className="hidden py-2 pr-4 text-muted md:table-cell">
                                                    {sync.user?.name ?? '—'}
                                                </td>
                                                <td className="hidden py-2 pr-4 text-muted tabular-nums lg:table-cell">
                                                    {sync.attempts}
                                                </td>
                                                <td className="py-2">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs text-subtle tabular-nums">
                                                            {new Date(
                                                                sync.created_at,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}
                                                        </span>
                                                        {can.review &&
                                                        (sync.status ===
                                                            'pending' ||
                                                            sync.status ===
                                                                'failed') ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={
                                                                    syncBusy
                                                                }
                                                                onClick={() =>
                                                                    retrySync(
                                                                        sync.id,
                                                                    )
                                                                }
                                                            >
                                                                <RefreshIcon
                                                                    size={13}
                                                                />
                                                                Retry
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </CardContent>
            </Card>
        </AppShell>
    );
}

type BucketedFindings = {
    confirmed: ExtractionField[];
    needsReview: ExtractionField[];
    low: ExtractionField[];
};

function bucketFindings(fields: ExtractionField[]): BucketedFindings {
    const confirmed: ExtractionField[] = [];
    const needsReview: ExtractionField[] = [];
    const low: ExtractionField[] = [];

    for (const field of fields) {
        if (field.confidence == null) {
            needsReview.push(field);
        } else if (field.confidence >= 85) {
            confirmed.push(field);
        } else if (field.confidence >= 60) {
            needsReview.push(field);
        } else {
            low.push(field);
        }
    }

    return { confirmed, needsReview, low };
}

function FindingGroup({
    title,
    description,
    variant,
    icon,
    fields,
    onEvidence,
}: {
    title: string;
    description: string;
    variant: 'green' | 'amber' | 'red';
    icon: React.ReactNode;
    fields: ExtractionField[];
    onEvidence: (page?: number) => void;
}) {
    if (fields.length === 0) {
        return null;
    }

    return (
        <section>
            <div
                className={`mb-2 flex items-center gap-2 text-xs font-semibold ${
                    variant === 'green'
                        ? 'text-success'
                        : variant === 'amber'
                          ? 'text-warning'
                          : 'text-danger'
                }`}
            >
                {icon}
                {title}
                <span className="font-normal text-subtle">— {description}</span>
            </div>
            <ul className="divide-y divide-border rounded-lg border border-border">
                {fields.map((field) => {
                    const hasEvidence =
                        field.evidence?.page != null ||
                        field.evidence?.source_text;

                    return (
                        <li
                            key={field.field}
                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="text-xs text-muted">
                                    {fieldLabel(field.field)}
                                </p>
                                <p
                                    className={`mt-0.5 text-sm font-medium break-words ${
                                        field.value
                                            ? 'text-foreground'
                                            : 'text-subtle'
                                    }`}
                                >
                                    {field.value || 'not found'}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span
                                    className="text-xs tabular-nums"
                                    style={{
                                        color:
                                            field.confidence == null
                                                ? undefined
                                                : confidenceColor(
                                                        field.confidence,
                                                    ) === 'success'
                                                  ? 'var(--success)'
                                                  : confidenceColor(
                                                          field.confidence,
                                                      ) === 'warning'
                                                    ? 'var(--warning)'
                                                    : 'var(--danger)',
                                    }}
                                >
                                    {confidenceLabel(field.confidence)}
                                </span>
                                <ConfidenceBar
                                    value={field.confidence}
                                    className="w-16"
                                />
                                {hasEvidence ? (
                                    <button
                                        type="button"
                                        className="cursor-pointer text-xs font-medium text-brand hover:underline"
                                        title={
                                            field.evidence?.source_text ??
                                            undefined
                                        }
                                        onClick={() =>
                                            onEvidence(field.evidence?.page)
                                        }
                                    >
                                        {field.evidence?.page != null
                                            ? `Show on page ${field.evidence.page}`
                                            : 'Show source'}
                                    </button>
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * Jump to the evidence location (spec §12): scrolls the page-state
 * card and highlights the target page.
 */
function jumpToEvidence(page?: number) {
    if (page == null) {
        return;
    }

    const target = document.getElementById(`document-page-${page}`);

    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('ring-2', 'ring-brand');
        window.setTimeout(
            () => target.classList.remove('ring-2', 'ring-brand'),
            2000,
        );
    }
}
