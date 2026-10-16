import { Head, Link } from '@inertiajs/react';
import { login } from '@/routes';
import {
    AiIcon,
    AnomaliesIcon,
    CheckCircleIcon,
    DocumentsIcon,
    HumanIcon,
    ScanIcon,
    SihandalIcon,
    UploadIcon,
    ValidationIcon,
} from '@/components/icons';
import { Badge, Button, ThemeSwitcher } from '@/components/ui';

const FEATURES = [
    {
        icon: ScanIcon,
        title: 'OCR that reads the real form',
        description:
            'Page-level text recognition for SPM, SP2D, SPP, DPA and RKA — with confidence scoring per page.',
    },
    {
        icon: AiIcon,
        title: 'AI extraction with evidence',
        description:
            'Every extracted field carries its source text and confidence, so reviewers see why the AI decided.',
    },
    {
        icon: ValidationIcon,
        title: 'Deterministic validation',
        description:
            'Totals, fiscal year and completeness checked by rules — not by a model. Anomalies block auto-approval.',
    },
    {
        icon: HumanIcon,
        title: 'Human-in-the-loop review',
        description:
            'AI recommends, people decide. Flagged documents route to a risk-ordered queue for a recorded decision.',
    },
    {
        icon: SihandalIcon,
        title: 'SIHANDAL system of record',
        description:
            'Confirmed documents sync to SIHANDAL through its REST API — the only write path, fully logged.',
    },
    {
        icon: AnomaliesIcon,
        title: 'Grounded AI assistant',
        description:
            'Ask questions over documents and SIHANDAL data. Answers are labeled FACT, CALCULATION, INTERPRETATION or WARNING.',
    },
];

const STEPS = [
    { icon: UploadIcon, label: 'Upload', detail: 'OCR + classify' },
    { icon: AiIcon, label: 'Extract', detail: 'AI with evidence' },
    { icon: ValidationIcon, label: 'Validate', detail: 'Rules + anomalies' },
    { icon: HumanIcon, label: 'Review', detail: 'Human decision' },
    { icon: SihandalIcon, label: 'Sync', detail: 'Written to SIHANDAL' },
];

const STATS = [
    { value: 'SPM · SP2D · SPP', label: 'Document types' },
    { value: 'OCR → AI → Rules', label: 'Processing pipeline' },
    { value: 'Read-only', label: 'AI assistant access' },
    { value: 'OPD-scoped', label: 'Data isolation' },
];

export default function Welcome() {
    return (
        <>
            <Head title="Financial document intelligence" />
            <div className="min-h-screen bg-background text-foreground">
                {/* Nav */}
                <header className="sticky top-0 z-30 border-b border-border bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                        <div className="flex items-center gap-2.5">
                            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-purple text-[13px] font-bold text-brand-foreground shadow-sm">
                                AF
                            </span>
                            <div className="leading-tight">
                                <p className="text-sm font-semibold tracking-tight">
                                    Agent Finance
                                </p>
                                <p className="text-[11px] text-subtle">
                                    Document intelligence
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <ThemeSwitcher className="hidden sm:inline-flex" />
                            <Link href={login.url()}>
                                <Button size="sm">Sign in</Button>
                            </Link>
                        </div>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div
                        aria-hidden
                        className="bg-grid pointer-events-none absolute inset-0 opacity-40 [mask-image:radial-gradient(ellipse_at_top,black,transparent_70%)]"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full bg-brand/10 blur-3xl"
                    />
                    <div className="relative mx-auto max-w-6xl px-4 pt-20 pb-16 text-center sm:px-6 sm:pt-28">
                        <Badge variant="blue" className="mx-auto">
                            <AiIcon size={12} />
                            Grounded, read-only financial AI
                        </Badge>
                        <h1 className="mx-auto mt-5 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Financial document intelligence for local government
                        </h1>
                        <p className="mx-auto mt-4 max-w-2xl text-base text-muted text-pretty sm:text-lg">
                            Upload SPM, SP2D, SPP, DPA and RKA files. Agent
                            Finance runs OCR, extracts structured fields with
                            evidence, validates them against deterministic rules
                            and syncs confirmed documents to SIHANDAL.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <Link href={login.url()}>
                                <Button size="md" className="h-10 px-5">
                                    Sign in to the workspace
                                </Button>
                            </Link>
                        </div>

                        {/* Mock panel */}
                        <div className="mx-auto mt-14 max-w-4xl">
                            <div className="rounded-2xl border border-border bg-surface p-2 shadow-lg">
                                <div className="flex items-center gap-1.5 px-3 py-2">
                                    <span className="h-2.5 w-2.5 rounded-full bg-danger/70" />
                                    <span className="h-2.5 w-2.5 rounded-full bg-warning/70" />
                                    <span className="h-2.5 w-2.5 rounded-full bg-success/70" />
                                </div>
                                <div className="grid gap-3 rounded-xl border border-border bg-background p-4 text-left sm:grid-cols-3">
                                    <div className="space-y-3 sm:col-span-2">
                                        <div className="flex items-center justify-between rounded-xl border border-border bg-surface p-3">
                                            <div>
                                                <p className="text-xs text-muted">
                                                    Extracted total
                                                </p>
                                                <p className="text-lg font-semibold tabular-nums">
                                                    Rp 1.248.500.000
                                                </p>
                                            </div>
                                            <Badge variant="green">
                                                <CheckCircleIcon size={12} />
                                                High confidence
                                            </Badge>
                                        </div>
                                        <div className="rounded-xl border border-border bg-surface p-3">
                                            <div className="mb-2 flex items-center justify-between">
                                                <p className="text-xs font-medium text-muted">
                                                    Validation
                                                </p>
                                                <Badge variant="green">
                                                    All checks passed
                                                </Badge>
                                            </div>
                                            <div className="space-y-1.5">
                                                <div className="h-2 w-full overflow-hidden rounded-full bg-elevated">
                                                    <div className="h-full w-[92%] rounded-full bg-success" />
                                                </div>
                                                <div className="h-2 w-full overflow-hidden rounded-full bg-elevated">
                                                    <div className="h-full w-[78%] rounded-full bg-brand" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="rounded-xl border border-border bg-surface p-3">
                                        <p className="text-xs font-medium text-muted">
                                            Pipeline
                                        </p>
                                        <ul className="mt-2 space-y-2">
                                            {STEPS.map((step) => (
                                                <li
                                                    key={step.label}
                                                    className="flex items-center gap-2 text-xs"
                                                >
                                                    <span className="flex h-6 w-6 items-center justify-center rounded-md bg-brand-subtle text-brand">
                                                        <step.icon size={13} />
                                                    </span>
                                                    <span className="font-medium">
                                                        {step.label}
                                                    </span>
                                                    <span className="text-subtle">
                                                        {step.detail}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Stats band */}
                <section className="border-y border-border bg-surface">
                    <div className="mx-auto grid max-w-6xl grid-cols-2 gap-px px-4 sm:grid-cols-4 sm:px-6">
                        {STATS.map((stat) => (
                            <div key={stat.label} className="px-2 py-6 text-center">
                                <p className="text-sm font-semibold tracking-tight text-foreground">
                                    {stat.value}
                                </p>
                                <p className="mt-0.5 text-xs text-muted">
                                    {stat.label}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Features */}
                <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            From PDF to the system of record
                        </h2>
                        <p className="mt-3 text-sm text-muted sm:text-base">
                            One pipeline that turns unstructured financial
                            documents into trustworthy, reviewable records.
                        </p>
                    </div>
                    <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((feature) => (
                            <div
                                key={feature.title}
                                className="rounded-xl border border-border bg-surface p-5 shadow-xs transition-shadow hover:shadow-md"
                            >
                                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-subtle text-brand">
                                    <feature.icon size={18} />
                                </span>
                                <h3 className="mt-4 text-sm font-semibold text-foreground">
                                    {feature.title}
                                </h3>
                                <p className="mt-1.5 text-sm text-muted">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Pipeline */}
                <section className="border-t border-border bg-surface">
                    <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                        <h2 className="text-center text-xl font-semibold tracking-tight">
                            A transparent, auditable pipeline
                        </h2>
                        <ol className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            {STEPS.map((step, index) => (
                                <li
                                    key={step.label}
                                    className="relative rounded-xl border border-border bg-background p-4"
                                >
                                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-brand to-purple text-brand-foreground shadow-sm">
                                        <step.icon size={16} />
                                    </span>
                                    <p className="mt-3 text-sm font-semibold">
                                        {index + 1}. {step.label}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted">
                                        {step.detail}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                {/* CTA */}
                <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div className="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-brand via-brand-strong to-purple px-6 py-14 text-center text-white shadow-lg sm:px-12">
                        <div
                            aria-hidden
                            className="bg-grid pointer-events-none absolute inset-0 opacity-[0.12]"
                        />
                        <div className="relative">
                            <DocumentsIcon
                                size={28}
                                className="mx-auto text-white/90"
                            />
                            <h2 className="mt-4 text-2xl font-semibold tracking-tight">
                                Ready to audit with confidence?
                            </h2>
                            <p className="mx-auto mt-2 max-w-xl text-sm text-white/80">
                                Sign in to upload documents, work the review
                                queue and ask the grounded assistant.
                            </p>
                            <div className="mt-6 flex justify-center">
                                <Link href={login.url()}>
                                    <Button
                                        size="md"
                                        className="h-10 bg-white px-5 text-brand-strong hover:bg-white/90"
                                    >
                                        Sign in
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-xs text-subtle sm:px-6">
                        <p>
                            Agent Finance · Financial document auditing for
                            local government
                        </p>
                        <p>Read-only assistant · Full audit trail</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
