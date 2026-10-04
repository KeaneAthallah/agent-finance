import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    EmptyState,
    Input,
} from '@/components/ui';

type StatementLabel = 'FACT' | 'CALCULATION' | 'AI INTERPRETATION' | 'WARNING';

type Statement = {
    label: StatementLabel;
    text: string;
    source: string | null;
};

type ToolCall = {
    name: string;
    arguments: Record<string, unknown>;
};

type Message = {
    role: 'user' | 'assistant';
    content: string;
    statements?: Statement[];
    toolCalls?: ToolCall[];
};

const LABEL_VARIANTS: Record<
    StatementLabel,
    'green' | 'blue' | 'purple' | 'amber'
> = {
    FACT: 'green',
    CALCULATION: 'blue',
    'AI INTERPRETATION': 'purple',
    WARNING: 'amber',
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match !== null ? decodeURIComponent(match[1]) : '';
}

export default function AiAssistant() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [question, setQuestion] = useState('');
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function send(event: FormEvent) {
        event.preventDefault();

        const trimmed = question.trim();

        if (trimmed === '' || pending) {
            return;
        }

        const userMessage: Message = { role: 'user', content: trimmed };

        setMessages((previous) => [...previous, userMessage]);
        setQuestion('');
        setPending(true);
        setError(null);

        try {
            const response = await fetch('/ai-assistant/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    question: trimmed,
                    history: messages.map((message) => ({
                        role: message.role,
                        content: message.content,
                    })),
                }),
            });

            if (!response.ok) {
                throw new Error(
                    `Assistant request failed (${response.status}).`,
                );
            }

            const data = (await response.json()) as {
                summary: string;
                statements: Statement[];
                tool_calls: ToolCall[];
            };

            setMessages((previous) => [
                ...previous,
                {
                    role: 'assistant',
                    content: data.summary,
                    statements: data.statements,
                    toolCalls: data.tool_calls,
                },
            ]);
        } catch (exception) {
            setMessages((previous) =>
                previous.filter((message) => message !== userMessage),
            );
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Assistant unavailable.',
            );
        } finally {
            setPending(false);
        }
    }

    return (
        <AppShell title="AI Assistant">
            <Head title="AI assistant" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Grounded financial Q&amp;A over your documents and the SIHANDAL
                system of record. Every statement is labeled:{' '}
                <Badge variant="green">FACT</Badge>{' '}
                <Badge variant="blue">CALCULATION</Badge>{' '}
                <Badge variant="purple">AI INTERPRETATION</Badge>{' '}
                <Badge variant="amber">WARNING</Badge> — financial numbers only
                ever come from tool results. The assistant is read-only.
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>Conversation</CardTitle>
                </CardHeader>
                <CardContent className="max-h-[60vh] space-y-4 overflow-y-auto p-4">
                    {messages.length === 0 ? (
                        <EmptyState
                            title="Ask about your financial data"
                            description="Examples: total budget for a fiscal year, search document content, compare a document with SIHANDAL, or validate a document."
                        />
                    ) : (
                        messages.map((message, index) =>
                            message.role === 'user' ? (
                                <div key={index} className="flex justify-end">
                                    <div className="max-w-[80%] rounded-lg bg-blue-700 px-3 py-2 text-sm text-white">
                                        {message.content}
                                    </div>
                                </div>
                            ) : (
                                <div
                                    key={index}
                                    className="max-w-[90%] space-y-2"
                                >
                                    {message.toolCalls !== undefined &&
                                        message.toolCalls.length > 0 && (
                                            <p className="text-xs text-neutral-400">
                                                Tools:{' '}
                                                {message.toolCalls
                                                    .map((tool) => tool.name)
                                                    .join(', ')}
                                            </p>
                                        )}
                                    {(message.statements ?? []).map(
                                        (statement, statementIndex) => (
                                            <div
                                                key={statementIndex}
                                                className="space-y-1"
                                            >
                                                <Badge
                                                    variant={
                                                        LABEL_VARIANTS[
                                                            statement.label
                                                        ]
                                                    }
                                                >
                                                    {statement.label}
                                                </Badge>
                                                <p className="text-sm text-neutral-800">
                                                    {statement.text}
                                                    {statement.source !==
                                                        null && (
                                                        <span className="ml-2 text-xs text-neutral-400">
                                                            source:{' '}
                                                            {statement.source}
                                                        </span>
                                                    )}
                                                </p>
                                            </div>
                                        ),
                                    )}
                                    {message.statements === undefined && (
                                        <div className="rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-800">
                                            {message.content}
                                        </div>
                                    )}
                                </div>
                            ),
                        )
                    )}
                    {error !== null && (
                        <p className="text-sm text-red-600">{error}</p>
                    )}
                </CardContent>
            </Card>

            <form onSubmit={send} className="mt-4 flex items-end gap-2">
                <div className="flex-1">
                    <Input
                        value={question}
                        onChange={(event) => setQuestion(event.target.value)}
                        placeholder="Ask about budgets, documents, SIHANDAL comparisons, validation..."
                        disabled={pending}
                        maxLength={2000}
                    />
                </div>
                <Button
                    type="submit"
                    disabled={pending || question.trim() === ''}
                >
                    {pending ? 'Thinking...' : 'Ask'}
                </Button>
            </form>
        </AppShell>
    );
}
