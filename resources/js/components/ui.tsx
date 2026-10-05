import { useEffect, useRef, useState } from 'react';
import type {
    ButtonHTMLAttributes,
    HTMLAttributes,
    InputHTMLAttributes,
    LabelHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { useTheme } from '@/lib/theme';
import {
    AlertCircleIcon,
    AiIcon,
    CheckCircleIcon,
    CircleIcon,
    ClockIcon,
    LayoutGridIcon,
    ListIcon,
    RefreshIcon,
    ReviewIcon,
    ScanIcon,
    SihandalIcon,
    ValidationIcon,
    MonitorIcon,
    MoonIcon,
    SunIcon,
} from '@/components/icons';

// Hand-rolled primitives in the shadcn/ui spirit (Tailwind v4, no runtime
// deps beyond clsx + tailwind-merge). Documented in ARCHITECTURE.md §3.2.
// All colors are semantic tokens (bg-surface, text-foreground, …) so every
// component adapts to light/dark themes automatically.

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'default' | 'outline' | 'ghost' | 'danger';
    size?: 'sm' | 'md';
};

export function Button({
    className,
    variant = 'default',
    size = 'md',
    ...props
}: ButtonProps) {
    return (
        <button
            className={cn(
                'inline-flex cursor-pointer items-center justify-center gap-2 rounded-md text-sm font-medium transition-colors',
                'focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-surface focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                size === 'sm' ? 'h-8 px-3' : 'h-10 px-4',
                variant === 'default' &&
                    'bg-brand text-brand-foreground hover:bg-brand-strong',
                variant === 'outline' &&
                    'border border-border-strong bg-surface text-foreground hover:bg-elevated',
                variant === 'ghost' &&
                    'text-muted hover:bg-elevated hover:text-foreground',
                variant === 'danger' && 'bg-danger text-white hover:opacity-90',
                className,
            )}
            {...props}
        />
    );
}

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'rounded-lg border border-border bg-surface shadow-sm',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('border-b border-border px-4 py-3', className)}
            {...props}
        />
    );
}

export function CardTitle({
    className,
    ...props
}: HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3
            className={cn('text-sm font-semibold text-foreground', className)}
            {...props}
        />
    );
}

export function CardDescription({
    className,
    ...props
}: HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p className={cn('mt-0.5 text-xs text-muted', className)} {...props} />
    );
}

export function CardContent({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-4 py-3', className)} {...props} />;
}

type BadgeVariant = 'neutral' | 'blue' | 'green' | 'amber' | 'red' | 'purple';

export function Badge({
    className,
    variant = 'neutral',
    ...props
}: HTMLAttributes<HTMLSpanElement> & { variant?: BadgeVariant }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                variant === 'neutral' &&
                    'border border-border bg-elevated text-muted',
                variant === 'blue' && 'bg-info-subtle text-info',
                variant === 'green' && 'bg-success-subtle text-success',
                variant === 'amber' && 'bg-warning-subtle text-warning',
                variant === 'red' && 'bg-danger-subtle text-danger',
                variant === 'purple' && 'bg-purple-subtle text-purple',
                className,
            )}
            {...props}
        />
    );
}

export function Input({
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            className={cn(
                'h-10 w-full rounded-md border border-border-strong bg-surface px-3 text-sm text-foreground',
                'placeholder:text-subtle focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none',
                className,
            )}
            {...props}
        />
    );
}

export function Select({
    className,
    ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn(
                'h-10 w-full cursor-pointer rounded-md border border-border-strong bg-surface px-2 text-sm text-foreground',
                'focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none',
                className,
            )}
            {...props}
        />
    );
}

export function Textarea({
    className,
    ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return (
        <textarea
            className={cn(
                'w-full rounded-md border border-border-strong bg-surface px-3 py-2 text-sm text-foreground',
                'placeholder:text-subtle focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none',
                className,
            )}
            {...props}
        />
    );
}

export function Label({
    className,
    ...props
}: LabelHTMLAttributes<HTMLLabelElement>) {
    return (
        <label
            className={cn(
                'mb-1 block text-sm font-medium text-muted',
                className,
            )}
            {...props}
        />
    );
}

export function EmptyState({
    title,
    description,
    action,
    icon,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    icon?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border-strong bg-elevated px-6 py-16 text-center">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-surface text-subtle">
                {icon ?? <InboxGlyph />}
            </div>
            <p className="text-sm font-semibold text-foreground">{title}</p>
            {description ? (
                <p className="max-w-md text-sm text-muted">{description}</p>
            ) : null}
            {action}
        </div>
    );
}

function InboxGlyph() {
    return (
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
        >
            <path d="M3 13.5 5.5 5h13L21 13.5V19a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19Z" />
            <path d="M3 13.5h5l1.5 2.5h5L16 13.5h5" />
        </svg>
    );
}

/* ------------------------------------------------------------------ */
/* Status system — icon + label + color, never color alone             */
/* ------------------------------------------------------------------ */

const STATUS_META: Record<
    string,
    { variant: BadgeVariant; label: string; icon: typeof CircleIcon }
> = {
    uploaded: { variant: 'neutral', label: 'Uploaded', icon: CircleIcon },
    queued: { variant: 'blue', label: 'Queued', icon: ClockIcon },
    processing: { variant: 'blue', label: 'Processing', icon: RefreshIcon },
    ocr: { variant: 'blue', label: 'OCR', icon: ScanIcon },
    ai_analysis: { variant: 'purple', label: 'AI analysis', icon: AiIcon },
    extraction_complete: {
        variant: 'blue',
        label: 'Extraction complete',
        icon: CheckCircleIcon,
    },
    validation: { variant: 'amber', label: 'Validation', icon: ValidationIcon },
    review_required: {
        variant: 'amber',
        label: 'Needs review',
        icon: ReviewIcon,
    },
    approved: { variant: 'green', label: 'Approved', icon: CheckCircleIcon },
    synced: { variant: 'purple', label: 'Synced', icon: SihandalIcon },
    completed: { variant: 'green', label: 'Completed', icon: CheckCircleIcon },
    processing_failed: {
        variant: 'red',
        label: 'Processing failed',
        icon: AlertCircleIcon,
    },
    ocr_failed: { variant: 'red', label: 'OCR failed', icon: AlertCircleIcon },
    ai_failed: { variant: 'red', label: 'AI failed', icon: AlertCircleIcon },
    validation_failed: {
        variant: 'red',
        label: 'Validation failed',
        icon: AlertCircleIcon,
    },
    sync_failed: {
        variant: 'red',
        label: 'Sync failed',
        icon: AlertCircleIcon,
    },
};

export function StatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const meta = STATUS_META[status] ?? {
        variant: 'neutral' as BadgeVariant,
        label: status.replaceAll('_', ' '),
        icon: CircleIcon,
    };
    const Icon = meta.icon;

    return (
        <Badge variant={meta.variant} className={className}>
            <Icon size={12} />
            {meta.label}
        </Badge>
    );
}

/* ------------------------------------------------------------------ */
/* KPI stat card                                                       */
/* ------------------------------------------------------------------ */

export function StatCard({
    icon,
    label,
    value,
    hint,
    variant = 'neutral',
    className,
}: {
    icon?: ReactNode;
    label: string;
    value: string | number;
    hint?: string;
    variant?: BadgeVariant;
    className?: string;
}) {
    return (
        <Card className={cn('p-4', className)}>
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium text-muted">{label}</p>
                {icon ? (
                    <span
                        className={cn(
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-md',
                            variant === 'green' &&
                                'bg-success-subtle text-success',
                            variant === 'amber' &&
                                'bg-warning-subtle text-warning',
                            variant === 'red' && 'bg-danger-subtle text-danger',
                            variant === 'blue' && 'bg-info-subtle text-info',
                            variant === 'purple' &&
                                'bg-purple-subtle text-purple',
                            variant === 'neutral' && 'bg-elevated text-muted',
                        )}
                    >
                        {icon}
                    </span>
                ) : null}
            </div>
            <p className="mt-1.5 text-2xl font-semibold text-foreground tabular-nums">
                {value}
            </p>
            {hint ? <p className="mt-0.5 text-xs text-subtle">{hint}</p> : null}
        </Card>
    );
}

/* ------------------------------------------------------------------ */
/* Confidence + progress                                               */
/* ------------------------------------------------------------------ */

export function confidenceColor(
    confidence: number | null,
): 'success' | 'warning' | 'danger' | 'neutral' {
    if (confidence == null) {
        return 'neutral';
    }

    if (confidence >= 85) {
        return 'success';
    }

    if (confidence >= 60) {
        return 'warning';
    }

    return 'danger';
}

export function ConfidenceBar({
    value,
    className,
}: {
    value: number | null;
    className?: string;
}) {
    const color = confidenceColor(value);

    return (
        <div
            className={cn(
                'h-1.5 w-full overflow-hidden rounded-full bg-elevated',
                className,
            )}
            role="img"
            aria-label={
                value == null
                    ? 'Confidence not available'
                    : `Confidence ${value}%`
            }
        >
            <div
                className={cn(
                    'h-full rounded-full transition-[width]',
                    color === 'success' && 'bg-success',
                    color === 'warning' && 'bg-warning',
                    color === 'danger' && 'bg-danger',
                    color === 'neutral' && 'bg-border-strong',
                )}
                style={{
                    width:
                        value == null
                            ? 0
                            : `${Math.min(100, Math.max(0, value))}%`,
                }}
            />
        </div>
    );
}

export function ProgressBar({
    value,
    variant = 'default',
    className,
}: {
    value: number;
    variant?: 'default' | 'success' | 'warning' | 'danger';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'h-1.5 w-full overflow-hidden rounded-full bg-elevated',
                className,
            )}
            role="progressbar"
            aria-valuenow={Math.round(value)}
            aria-valuemin={0}
            aria-valuemax={100}
        >
            <div
                className={cn(
                    'h-full rounded-full transition-[width]',
                    variant === 'default' && 'bg-brand',
                    variant === 'success' && 'bg-success',
                    variant === 'warning' && 'bg-warning',
                    variant === 'danger' && 'bg-danger',
                )}
                style={{ width: `${Math.min(100, Math.max(0, value))}%` }}
            />
        </div>
    );
}

export function Skeleton({ className }: { className?: string }) {
    return (
        <div
            className={cn('animate-pulse rounded-md bg-elevated', className)}
            aria-hidden
        />
    );
}

/* ------------------------------------------------------------------ */
/* Tabs + view switcher                                                */
/* ------------------------------------------------------------------ */

export function Tabs({
    options,
    value,
    onChange,
    className,
}: {
    options: { value: string; label: string }[];
    value: string;
    onChange: (value: string) => void;
    className?: string;
}) {
    return (
        <div
            role="tablist"
            className={cn(
                'inline-flex items-center gap-1 rounded-md border border-border bg-elevated p-1',
                className,
            )}
        >
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="tab"
                    aria-selected={option.value === value}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'cursor-pointer rounded px-2.5 py-1.5 text-xs font-medium transition-colors',
                        option.value === value
                            ? 'bg-surface text-foreground shadow-sm'
                            : 'text-muted hover:text-foreground',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

export function ViewSwitcher({
    view,
    onChange,
}: {
    view: 'table' | 'cards';
    onChange: (view: 'table' | 'cards') => void;
}) {
    return (
        <div
            role="group"
            aria-label="View"
            className="inline-flex items-center gap-1 rounded-md border border-border bg-elevated p-1"
        >
            <button
                type="button"
                aria-label="Table view"
                aria-pressed={view === 'table'}
                onClick={() => onChange('table')}
                className={cn(
                    'flex cursor-pointer items-center rounded p-1.5 transition-colors',
                    view === 'table'
                        ? 'bg-surface text-foreground shadow-sm'
                        : 'text-muted hover:text-foreground',
                )}
            >
                <ListIcon size={15} />
            </button>
            <button
                type="button"
                aria-label="Card view"
                aria-pressed={view === 'cards'}
                onClick={() => onChange('cards')}
                className={cn(
                    'flex cursor-pointer items-center rounded p-1.5 transition-colors',
                    view === 'cards'
                        ? 'bg-surface text-foreground shadow-sm'
                        : 'text-muted hover:text-foreground',
                )}
            >
                <LayoutGridIcon size={15} />
            </button>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Page header + section heading                                       */
/* ------------------------------------------------------------------ */

export function PageHeader({
    title,
    description,
    actions,
    className,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mb-6 flex flex-wrap items-start justify-between gap-3',
                className,
            )}
        >
            <div className="min-w-0">
                <h1 className="text-xl font-semibold tracking-tight text-foreground">
                    {title}
                </h1>
                {description ? (
                    <p className="mt-1 max-w-3xl text-sm text-muted">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

export function SectionTitle({
    title,
    description,
    actions,
    className,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mb-4 flex flex-wrap items-start justify-between gap-2',
                className,
            )}
        >
            <div className="min-w-0">
                <h2 className="text-base font-semibold text-foreground">
                    {title}
                </h2>
                {description ? (
                    <p className="mt-0.5 text-sm text-muted">{description}</p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Pagination                                                          */
/* ------------------------------------------------------------------ */

export function PaginationLinks({
    links,
    className,
}: {
    links: { url: string | null; label: string; active: boolean }[];
    className?: string;
}) {
    return (
        <nav
            aria-label="Pagination"
            className={cn('flex items-center gap-1', className)}
        >
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        aria-current={link.active ? 'page' : undefined}
                        className={cn(
                            'inline-flex h-8 min-w-8 cursor-pointer items-center justify-center rounded-md border px-2 text-xs font-medium transition-colors',
                            link.active
                                ? 'border-brand bg-brand-subtle text-brand'
                                : 'border-border bg-surface text-muted hover:bg-elevated hover:text-foreground',
                        )}
                        dangerouslySetInnerHTML={{
                            __html: link.label
                                .replace(/&laquo;|&raquo;/g, '')
                                .replace(/&amp;/g, '&'),
                        }}
                    />
                ) : (
                    <span
                        key={index}
                        className="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-border bg-surface px-2 text-xs text-subtle"
                        dangerouslySetInnerHTML={{
                            __html: link.label
                                .replace(/&laquo;|&raquo;/g, '')
                                .replace(/&amp;/g, '&'),
                        }}
                    />
                ),
            )}
        </nav>
    );
}

/* ------------------------------------------------------------------ */
/* Dropdown (simple, keyboard-escapable)                               */
/* ------------------------------------------------------------------ */

export function Dropdown({
    trigger,
    children,
    align = 'right',
    label,
}: {
    trigger: ReactNode;
    children: ReactNode;
    align?: 'left' | 'right';
    label: string;
}) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (
                rootRef.current &&
                !rootRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        const onKeydown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeydown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeydown);
        };
    }, [open]);

    return (
        <div ref={rootRef} className="relative">
            <button
                type="button"
                aria-label={label}
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => setOpen((value) => !value)}
                className="cursor-pointer"
            >
                {trigger}
            </button>
            {open ? (
                <div
                    role="menu"
                    className={cn(
                        'absolute z-40 mt-2 w-56 rounded-lg border border-border bg-surface p-1.5 shadow-lg',
                        align === 'right' ? 'right-0' : 'left-0',
                    )}
                >
                    {children}
                </div>
            ) : null}
        </div>
    );
}

export function DropdownItem({
    icon,
    children,
    onClick,
    danger,
    disabled,
}: {
    icon?: ReactNode;
    children: ReactNode;
    onClick?: () => void;
    danger?: boolean;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            role="menuitem"
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'flex w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition-colors',
                danger
                    ? 'text-danger hover:bg-danger-subtle'
                    : 'text-foreground hover:bg-elevated',
                disabled && 'pointer-events-none opacity-50',
            )}
        >
            {icon}
            {children}
        </button>
    );
}

/* ------------------------------------------------------------------ */
/* Theme switcher                                                      */
/* ------------------------------------------------------------------ */

export function ThemeSwitcher({ className }: { className?: string }) {
    const { preference, setPreference } = useTheme();

    return (
        <div
            role="group"
            aria-label="Theme"
            className={cn(
                'inline-flex items-center gap-1 rounded-md border border-border bg-elevated p-1',
                className,
            )}
        >
            {(
                [
                    { value: 'light', label: 'Light', icon: SunIcon },
                    { value: 'dark', label: 'Dark', icon: MoonIcon },
                    { value: 'system', label: 'System', icon: MonitorIcon },
                ] as const
            ).map((option) => (
                <button
                    key={option.value}
                    type="button"
                    aria-label={option.label}
                    aria-pressed={preference === option.value}
                    title={option.label}
                    onClick={() => setPreference(option.value)}
                    className={cn(
                        'flex cursor-pointer items-center rounded p-1.5 transition-colors',
                        preference === option.value
                            ? 'bg-surface text-foreground shadow-sm'
                            : 'text-muted hover:text-foreground',
                    )}
                >
                    <option.icon size={15} />
                </button>
            ))}
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Workflow stepper                                                    */
/* ------------------------------------------------------------------ */

export type WorkflowStage = {
    key: string;
    label: string;
    count?: number;
    state?: 'pending' | 'active' | 'done' | 'failed';
};

export function WorkflowStepper({
    stages,
    className,
}: {
    stages: WorkflowStage[];
    className?: string;
}) {
    return (
        <ol
            aria-label="Document workflow"
            className={cn(
                'scroll-thin flex items-start overflow-x-auto',
                className,
            )}
        >
            {stages.map((stage, index) => {
                const state = stage.state ?? 'pending';
                const Icon =
                    state === 'done'
                        ? CheckCircleIcon
                        : state === 'failed'
                          ? AlertCircleIcon
                          : state === 'active'
                            ? RefreshIcon
                            : CircleIcon;

                return (
                    <li
                        key={stage.key}
                        className="flex min-w-28 flex-1 items-start"
                    >
                        <div className="flex flex-col items-center text-center">
                            <span
                                className={cn(
                                    'flex h-8 w-8 items-center justify-center rounded-full border',
                                    state === 'done' &&
                                        'border-success/30 bg-success-subtle text-success',
                                    state === 'active' &&
                                        'border-brand/40 bg-brand-subtle text-brand',
                                    state === 'failed' &&
                                        'border-danger/30 bg-danger-subtle text-danger',
                                    state === 'pending' &&
                                        'border-border bg-elevated text-subtle',
                                )}
                            >
                                <Icon
                                    size={15}
                                    className={
                                        state === 'active' ? 'animate-spin' : ''
                                    }
                                />
                            </span>
                            <p
                                className={cn(
                                    'mt-1.5 text-[11px] leading-tight font-medium',
                                    state === 'pending'
                                        ? 'text-subtle'
                                        : 'text-foreground',
                                )}
                            >
                                {stage.label}
                            </p>
                            {stage.count != null ? (
                                <p className="text-[11px] text-muted tabular-nums">
                                    {stage.count}
                                </p>
                            ) : null}
                        </div>
                        {index < stages.length - 1 ? (
                            <span
                                aria-hidden
                                className={cn(
                                    'mx-1 mt-4 h-px min-w-4 flex-1',
                                    state === 'done'
                                        ? 'bg-success/40'
                                        : 'bg-border',
                                )}
                            />
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}

/* ------------------------------------------------------------------ */
/* Field row (metadata)                                                */
/* ------------------------------------------------------------------ */

export function FieldRow({
    label,
    value,
    className,
}: {
    label: string;
    value: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-start justify-between gap-4 border-b border-border py-2 last:border-0',
                className,
            )}
        >
            <span className="shrink-0 text-sm text-muted">{label}</span>
            <span className="min-w-0 text-right font-medium break-words text-foreground">
                {value}
            </span>
        </div>
    );
}
