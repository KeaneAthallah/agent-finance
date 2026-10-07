import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Markdown } from './markdown';

describe('Markdown', () => {
    it('renders GFM tables', () => {
        render(
            <Markdown>{`| Tahun | Pagu |\n| --- | --- |\n| 2026 | Rp1 T |`}</Markdown>,
        );

        expect(screen.getByRole('table')).toBeInTheDocument();
        expect(screen.getByText('Pagu')).toBeInTheDocument();
        expect(screen.getByText('Rp1 T')).toBeInTheDocument();
    });

    it('does not render raw HTML, keeping it escaped text', () => {
        render(<Markdown>{'<script>alert(1)</script>'}</Markdown>);

        expect(document.querySelector('script')).toBeNull();
        expect(screen.getByText('script', { exact: false })).toBeInTheDocument();
    });

    it('renders lists and headings', () => {
        render(<Markdown>{'## Ringkasan\n\n1. satu\n2. dua'}</Markdown>);

        expect(screen.getByRole('heading', { name: 'Ringkasan' })).toBeInTheDocument();
        expect(screen.getByText('satu')).toBeInTheDocument();
        expect(screen.getByText('dua')).toBeInTheDocument();
    });

    it('strips javascript: link protocols', () => {
        render(<Markdown>{'[bad](javascript:alert(1))'}</Markdown>);

        const link = screen.getByText('bad');

        expect(link.getAttribute('href') ?? '').not.toContain('javascript:');
    });

    it('renders bold and escaped code fences', () => {
        render(<Markdown>{'**signifikansi**\n\n```js\nconst x = 1;\n```'}</Markdown>);

        expect(screen.getByText('signifikansi').tagName).toBe('STRONG');
        expect(screen.getByText('const x = 1;', { exact: false })).toBeInTheDocument();
    });
});