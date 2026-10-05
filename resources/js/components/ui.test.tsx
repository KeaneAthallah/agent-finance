import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    EmptyState,
    Input,
    Label,
} from '@/components/ui';

describe('ui primitives', () => {
    it('renders a badge with its label', () => {
        render(<Badge variant="green">active</Badge>);

        expect(screen.getByText('active')).toBeInTheDocument();
    });

    it('renders a card with header and content', () => {
        render(
            <Card>
                <CardHeader>
                    <CardTitle>Reports</CardTitle>
                </CardHeader>
                <CardContent>
                    <p>Body copy</p>
                </CardContent>
            </Card>,
        );

        expect(screen.getByText('Reports')).toBeInTheDocument();
        expect(screen.getByText('Body copy')).toBeInTheDocument();
    });

    it('renders a labelled input', () => {
        render(
            <div>
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" placeholder="you@example.com" />
            </div>,
        );

        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(
            screen.getByPlaceholderText('you@example.com'),
        ).toBeInTheDocument();
    });

    it('renders an empty state with optional description', () => {
        render(
            <EmptyState
                title="No documents"
                description="Upload a financial document to begin."
            />,
        );

        expect(screen.getByText('No documents')).toBeInTheDocument();
        expect(
            screen.getByText('Upload a financial document to begin.'),
        ).toBeInTheDocument();
    });

    it('renders a submit button', () => {
        render(<Button type="submit">Sign in</Button>);

        expect(
            screen.getByRole('button', { name: 'Sign in' }),
        ).toBeInTheDocument();
    });
});
