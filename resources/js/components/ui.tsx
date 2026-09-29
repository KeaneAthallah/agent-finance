import type {
    ButtonHTMLAttributes,
    HTMLAttributes,
    InputHTMLAttributes,
    LabelHTMLAttributes,
} from 'react';
import { cn } from '@/lib/utils';

// Hand-rolled primitives in the shadcn/ui spirit (Tailwind v4, no runtime
// deps beyond clsx + tailwind-merge). Documented in ARCHITECTURE.md §3.2.

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
                'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition-colors',
                'focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none',
                'disabled:pointer-events-none disabled:opacity-50',
                size === 'sm' ? 'h-8 px-3' : 'h-10 px-4',
                variant === 'default' &&
                    'bg-blue-700 text-white hover:bg-blue-800',
                variant === 'outline' &&
                    'border border-neutral-300 bg-white text-neutral-900 hover:bg-neutral-50',
                variant === 'ghost' && 'text-neutral-700 hover:bg-neutral-100',
                variant === 'danger' &&
                    'bg-red-700 text-white hover:bg-red-800',
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
                'rounded-lg border border-neutral-200 bg-white shadow-sm',
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
            className={cn('border-b border-neutral-200 px-4 py-3', className)}
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
            className={cn('text-sm font-semibold text-neutral-900', className)}
            {...props}
        />
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
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                variant === 'neutral' && 'bg-neutral-100 text-neutral-700',
                variant === 'blue' && 'bg-blue-50 text-blue-700',
                variant === 'green' && 'bg-green-50 text-green-700',
                variant === 'amber' && 'bg-amber-50 text-amber-700',
                variant === 'red' && 'bg-red-50 text-red-700',
                variant === 'purple' && 'bg-purple-50 text-purple-700',
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
                'h-10 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-900',
                'placeholder:text-neutral-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 focus:outline-none',
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
                'mb-1 block text-sm font-medium text-neutral-700',
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
}: {
    title: string;
    description?: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-6 py-16 text-center">
            <p className="text-sm font-semibold text-neutral-900">{title}</p>
            {description ? (
                <p className="max-w-md text-sm text-neutral-500">
                    {description}
                </p>
            ) : null}
            {action}
        </div>
    );
}
