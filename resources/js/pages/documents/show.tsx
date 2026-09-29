import { Head, router } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
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

type Props = InertiaPageProps & {
    document: DocumentDetail;
    previewUrl: string;
    pipeline: PipelineRun[];
    pages: PageState[];
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

const STAGE_ORDER = ['text_extraction', 'ocr', 'classification', 'extraction', 'validation', 'matching'];

export default function DocumentShow({ document, previewUrl, pipeline, pages }: Props) {
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
            </div>            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Preview</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="flex h-64 items-center justify-center rounded border border-dashed border-neutral-300 bg-neutral-50 text-sm text-neutral-500">
                            Document viewer lands with the AI extraction work (Phase 4).
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
            </div>            <div className="mt-4 grid gap-4 lg:grid-cols-2">
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
                                    <li key={stage} className="flex items-center justify-between text-sm">
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
                                                {state === 'done' ? '✓' : state === 'failed' ? '✕' : state === 'running' ? '●' : '○'}
                                            </span>
                                            <span className={state === 'pending' ? 'text-neutral-400' : 'text-neutral-800'}>
                                                {STAGE_LABELS[stage] ?? stage}
                                            </span>
                                        </span>
                                        {run?.error ? (
                                            <span className="max-w-[50%] truncate text-xs text-red-600" title={run.error}>
                                                {run.error}
                                            </span>
                                        ) : run && run.duration_ms != null ? (
                                            <span className="text-xs tabular-nums text-neutral-400">
                                                {(run.duration_ms / 1000).toFixed(1)}s
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
                                onClick={() => router.post(`/documents/${document.id}/process`)}
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
                            <p className="text-sm text-neutral-500">Not processed yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs uppercase tracking-wide text-neutral-500">
                                        <th className="py-1.5 pr-3 font-medium">Page</th>
                                        <th className="py-1.5 pr-3 font-medium">Source</th>
                                        <th className="py-1.5 font-medium">Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pages.map((page) => (
                                        <tr key={page.page_number} className="border-b border-neutral-100">
                                            <td className="py-1.5 pr-3 tabular-nums">{page.page_number}</td>
                                            <td className="py-1.5 pr-3">{page.text_source ?? '—'}</td>
                                            <td className="py-1.5 tabular-nums">
                                                {page.ocr_confidence != null ? `${page.ocr_confidence}%` : '—'}
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
                    </CardHeader>
                    <CardContent className="p-4 text-sm text-neutral-500">
                        Structured extraction with confidence and evidence lands in Phase 4.
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Validation</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 text-sm text-neutral-500">
                        Validation engine results land in Phase 5.
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
