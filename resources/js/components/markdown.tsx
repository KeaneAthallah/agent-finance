import { ComponentPropsWithoutRef, type ReactNode } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

function proseClass(element: string, extra?: string): string {
    return cn(
        'text-sm leading-relaxed',
        element === 'p' && 'mb-3 last:mb-0',
        element === 'strong' && 'font-semibold text-foreground',
        element === 'em' && 'italic',
        extra,
    );
}

const components = {
    h1: ({ ...props }: ComponentPropsWithoutRef<'h1'>) => (
        <h1 className="mb-2 mt-4 text-base font-semibold" {...props} />
    ),
    h2: ({ ...props }: ComponentPropsWithoutRef<'h2'>) => (
        <h2 className="mb-2 mt-4 text-sm font-semibold" {...props} />
    ),
    h3: ({ ...props }: ComponentPropsWithoutRef<'h3'>) => (
        <h3 className="mb-2 mt-3 text-sm font-semibold" {...props} />
    ),
    p: ({ ...props }: ComponentPropsWithoutRef<'p'>) => (
        <p className={proseClass('p')} {...props} />
    ),
    ul: ({ ...props }: ComponentPropsWithoutRef<'ul'>) => (
        <ul className="mb-3 list-disc space-y-1 pl-5" {...props} />
    ),
    ol: ({ ...props }: ComponentPropsWithoutRef<'ol'>) => (
        <ol className="mb-3 list-decimal space-y-1 pl-5" {...props} />
    ),
    li: ({ ...props }: ComponentPropsWithoutRef<'li'>) => (
        <li className="text-sm leading-relaxed" {...props} />
    ),
    a: ({ ...props }: ComponentPropsWithoutRef<'a'>) => (
        <a
            className="text-brand underline underline-offset-2 hover:opacity-80"
            target="_blank"
            rel="noreferrer noopener"
            {...props}
        />
    ),
    blockquote: ({ ...props }: ComponentPropsWithoutRef<'blockquote'>) => (
        <blockquote
            className="mb-3 border-l-2 border-border pl-3 text-subtle"
            {...props}
        />
    ),
    code: ({ ...props }: ComponentPropsWithoutRef<'code'>) => (
        <code
            className="rounded bg-elevated px-1 py-0.5 font-mono text-xs"
            {...props}
        />
    ),
    pre: ({ ...props }: ComponentPropsWithoutRef<'pre'>) => (
        <pre
            className="mb-3 overflow-x-auto rounded-lg border border-border bg-elevated p-3 text-xs"
            {...props}
        />
    ),
    table: ({ ...props }: ComponentPropsWithoutRef<'table'>) => (
        <div className="mb-3 overflow-x-auto rounded-lg border border-border">
            <table className="w-full border-collapse text-sm" {...props} />
        </div>
    ),
    thead: ({ ...props }: ComponentPropsWithoutRef<'thead'>) => (
        <thead className="bg-elevated" {...props} />
    ),
    th: ({ ...props }: ComponentPropsWithoutRef<'th'>) => (
        <th
            className="border-b border-border px-3 py-2 text-left text-xs font-semibold"
            {...props}
        />
    ),
    td: ({ ...props }: ComponentPropsWithoutRef<'td'>) => (
        <td
            className="border-b border-border px-3 py-2 align-top text-sm"
            {...props}
        />
    ),
    hr: ({ ...props }: ComponentPropsWithoutRef<'hr'>) => (
        <hr className="my-3 border-border" {...props} />
    ),
};

export function Markdown({
    children,
    className,
}: {
    children: string;
    className?: string;
}): ReactNode {
    return (
        <div className={cn('space-y-0.5', className)} data-testid="markdown">
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {children}
            </ReactMarkdown>
        </div>
    );
}