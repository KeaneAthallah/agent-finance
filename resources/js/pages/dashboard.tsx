import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import { usePage } from '@inertiajs/react';
import AiAssistantController from '@/actions/App/Http/Controllers/AiAssistantController';
import AnomalyController from '@/actions/App/Http/Controllers/AnomalyController';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import ReportsController from '@/actions/App/Http/Controllers/ReportsController';
import ReviewController from '@/actions/App/Http/Controllers/ReviewController';
import SihandalController from '@/actions/App/Http/Controllers/SihandalController';
import {
    ActivityIcon,
    AnomaliesIcon,
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    DocumentsIcon,
    RefreshIcon,
    ReviewIcon,
    ScanIcon,
    SihandalIcon,
    ValidationIcon,
} from '@/components/icons';
import {
    Badge,
    Card,
    CardContent,
    StatCard,
    WorkflowStepper,
} from '@/components/ui';
import type { WorkflowStage } from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type Counters = {
    uploaded: number;
    processing: number;
    needs_review: number;
    approved: number;
    synced: number;
    failed: number;
};

type Props = InertiaPageProps & {
    counters: Counters;
};

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Good morning';
    }

    if (hour < 15) {
        return 'Good afternoon';
    }

    if (hour < 19) {
        return 'Good evening';
    }

    return 'Good night';
}

export default function Dashboard({ counters }: Props) {
    const user = usePage().props.auth.user;
    const firstName = user.name.split(' ')[0] ?? user.name;

    const kpis = useMemo(
        () => [
            {
                label: 'Documents',
                value: counters.uploaded,
                hint: 'uploaded',
                variant: 'neutral' as const,
                icon: <DocumentsIcon size={15} />,
                href: DocumentController.index.url(),
            },
            {
                label: 'Processing',
                value: counters.processing,
                hint: 'in pipeline',
                variant: 'blue' as const,
                icon: <RefreshIcon size={15} />,
                href: DocumentController.index.url({
                    query: { status: 'processing' },
                }),
            },
            {
                label: 'Needs review',
                value: counters.needs_review,
                hint: 'waiting for a human',
                variant: 'amber' as const,
                icon: <ReviewIcon size={15} />,
                href: ReviewController.index.url(),
            },
            {
                label: 'Approved',
                value: counters.approved,
                hint: 'verified by humans',
                variant: 'green' as const,
                icon: <CheckCircleIcon size={15} />,
                href: DocumentController.index.url({
                    query: { status: 'approved' },
                }),
            },
            {
                label: 'Synced',
                value: counters.synced,
                hint: 'sent to SIHANDAL',
                variant: 'purple' as const,
                icon: <SihandalIcon size={15} />,
                href: SihandalController.index.url(),
            },
            {
                label: 'Failed',
                value: counters.failed,
                hint: 'need attention',
                variant: 'red' as const,
                icon: <AnomaliesIcon size={15} />,
                href: DocumentController.index.url({
                    query: { status: 'processing_failed' },
                }),
            },
        ],
        [counters],
    );

    // The pipeline stages map onto the real status counters.
    // Extract / analyze / validate share the "processing" counter —
    // the pipeline does not fabricate per-stage counts.
    const stages: WorkflowStage[] = useMemo(
        () => [
            {
                key: 'upload',
                label: 'Upload',
                count: counters.uploaded,
                state: counters.uploaded > 0 ? 'done' : 'pending',
            },
            {
                key: 'extract',
                label: 'Extract',
                count: counters.processing,
                state: counters.processing > 0 ? 'active' : 'pending',
            },
            {
                key: 'analyze',
                label: 'Analyze',
                count: counters.processing,
                state: counters.processing > 0 ? 'active' : 'pending',
            },
            {
                key: 'validate',
                label: 'Validate',
                count: counters.processing,
                state: counters.processing > 0 ? 'active' : 'pending',
            },
            {
                key: 'review',
                label: 'Review',
                count: counters.needs_review,
                state: counters.needs_review > 0 ? 'active' : 'pending',
            },
            {
                key: 'confirm',
                label: 'Confirm',
                count: counters.approved,
                state: counters.approved > 0 ? 'done' : 'pending',
            },
            {
                key: 'sync',
                label: 'Sync',
                count: counters.synced,
                state: counters.synced > 0 ? 'done' : 'pending',
            },
        ],
        [counters],
    );

    return (
        <AppShell
            title="Dashboard"
            subtitle="Current state of your financial document workflow"
        >
            <Head title="Dashboard" />

            {/* Greeting */}
            <div className="mb-6">
                <h1 className="text-xl font-semibold tracking-tight text-foreground">
                    {greeting()}, {firstName}
                </h1>
                <p className="mt-1 text-sm text-muted">
                    Here is what is happening with your financial documents
                    right now.
                </p>
            </div>

            {/* KPIs */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                {kpis.map((kpi) => (
                    <Link
                        key={kpi.label}
                        href={kpi.href}
                        className="cursor-pointer rounded-lg transition-transform hover:-translate-y-px"
                    >
                        <StatCard
                            label={kpi.label}
                            value={kpi.value}
                            hint={kpi.hint}
                            variant={kpi.variant}
                            icon={kpi.icon}
                        />
                    </Link>
                ))}
            </div>

            {/* Workflow */}
            <Card className="mt-4">
                <CardContent className="p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold text-foreground">
                            Document workflow
                        </h2>
                        {counters.failed > 0 ? (
                            <Badge variant="red">
                                <AnomaliesIcon size={12} />
                                {counters.failed} failed
                            </Badge>
                        ) : (
                            <Badge variant="green">
                                <CheckCircleIcon size={12} />
                                Pipeline healthy
                            </Badge>
                        )}
                    </div>
                    <WorkflowStepper stages={stages} />
                </CardContent>
            </Card>

            {/* Attention + orientation */}
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent className="p-5">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <ClockIcon size={15} className="text-muted" />
                            Needs attention
                        </h2>
                        {counters.needs_review === 0 &&
                        counters.failed === 0 ? (
                            <p className="mt-2 text-sm text-muted">
                                Nothing is waiting. Documents that need a human
                                decision appear here the moment the validation
                                engine flags them.
                            </p>
                        ) : (
                            <ul className="mt-3 space-y-2">
                                {counters.needs_review > 0 ? (
                                    <li>
                                        <Link
                                            href={ReviewController.index.url()}
                                            className="group flex items-center justify-between rounded-md border border-border bg-elevated px-3 py-2.5 text-sm transition-colors hover:border-brand/40"
                                        >
                                            <span className="flex items-center gap-2.5 text-foreground">
                                                <ReviewIcon
                                                    size={15}
                                                    className="text-warning"
                                                />
                                                {counters.needs_review} document
                                                {counters.needs_review === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                waiting for review
                                            </span>
                                            <span className="text-xs font-medium text-brand">
                                                Open queue
                                            </span>
                                        </Link>
                                    </li>
                                ) : null}
                                {counters.failed > 0 ? (
                                    <li>
                                        <Link
                                            href={DocumentController.index.url({
                                                query: {
                                                    status: 'processing_failed',
                                                },
                                            })}
                                            className="group flex items-center justify-between rounded-md border border-border bg-elevated px-3 py-2.5 text-sm transition-colors hover:border-brand/40"
                                        >
                                            <span className="flex items-center gap-2.5 text-foreground">
                                                <AnomaliesIcon
                                                    size={15}
                                                    className="text-danger"
                                                />
                                                {counters.failed} failed
                                                document
                                                {counters.failed === 1
                                                    ? ''
                                                    : 's'}
                                            </span>
                                            <span className="text-xs font-medium text-brand">
                                                View
                                            </span>
                                        </Link>
                                    </li>
                                ) : null}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5">
                        <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <ActivityIcon size={15} className="text-muted" />
                            How the pipeline works
                        </h2>
                        <ol className="mt-3 space-y-2.5 text-sm text-muted">
                            <li className="flex items-start gap-2.5">
                                <ScanIcon
                                    size={15}
                                    className="mt-0.5 shrink-0 text-info"
                                />
                                <span>
                                    Upload a financial document — OCR and AI
                                    extraction pull out the structured fields
                                    with evidence.
                                </span>
                            </li>
                            <li className="flex items-start gap-2.5">
                                <ValidationIcon
                                    size={15}
                                    className="mt-0.5 shrink-0 text-warning"
                                />
                                <span>
                                    The validation engine checks totals, fiscal
                                    year and completeness — anomalies block
                                    automatic approval.
                                </span>
                            </li>
                            <li className="flex items-start gap-2.5">
                                <ReviewIcon
                                    size={15}
                                    className="mt-0.5 shrink-0 text-warning"
                                />
                                <span>
                                    A human reviews AI output and confirms or
                                    corrects it. AI recommends, humans decide.
                                </span>
                            </li>
                            <li className="flex items-start gap-2.5">
                                <SihandalIcon
                                    size={15}
                                    className="mt-0.5 shrink-0 text-purple"
                                />
                                <span>
                                    Confirmed documents sync to SIHANDAL — the
                                    system of record — through the API.
                                </span>
                            </li>
                        </ol>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Link
                                href={AiAssistantController.index.url()}
                                className="text-xs font-medium text-brand hover:underline"
                            >
                                Ask the AI assistant
                            </Link>
                            <span className="text-xs text-subtle">·</span>
                            <Link
                                href={AnomalyController.index.url()}
                                className="text-xs font-medium text-brand hover:underline"
                            >
                                Browse anomalies
                            </Link>
                            <span className="text-xs text-subtle">·</span>
                            <Link
                                href={ReportsController.index.url()}
                                className="text-xs font-medium text-brand hover:underline"
                            >
                                View reports
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {counters.uploaded === 0 ? (
                <Card className="mt-4">
                    <CardContent className="flex flex-col items-center gap-2 px-6 py-12 text-center">
                        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-subtle text-brand">
                            <CircleIcon size={20} />
                        </span>
                        <p className="text-sm font-semibold text-foreground">
                            No documents yet
                        </p>
                        <p className="max-w-md text-sm text-muted">
                            Upload your first financial document to begin
                            extraction, validation and SIHANDAL matching.
                        </p>
                        <Link
                            href={DocumentController.index.url()}
                            className="mt-2 inline-flex h-9 items-center rounded-md bg-brand px-4 text-sm font-medium text-brand-foreground transition-colors hover:bg-brand-strong"
                        >
                            Go to documents
                        </Link>
                    </CardContent>
                </Card>
            ) : null}
        </AppShell>
    );
}
