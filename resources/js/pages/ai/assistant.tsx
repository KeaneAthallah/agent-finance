import { Head } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import AiAssistantController from '@/actions/App/Http/Controllers/AiAssistantController';
import {
    AlertCircleIcon,
    AiIcon,
    CheckCircleIcon,
    CpuIcon,
    InfoIcon,
    SendIcon,
} from '@/components/icons';
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

const LABEL_ICONS: Record<StatementLabel, typeof CheckCircleIcon> = {
    FACT: CheckCircleIcon,
    CALCULATION: CpuIcon,
    'AI INTERPRETATION': AiIcon,
    WARNING: AlertCircleIcon,
};

const LABEL_EXPLANATIONS: Record<StatementLabel, string> = {
    FACT: 'Verified against document content or SIHANDAL data',
    CALCULATION: 'Computed from extracted financial values',
    'AI INTERPRETATION': 'AI reasoning — verify before relying on it',
    WARNING: 'Potential inconsistency worth checking',
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
            const response = await fetch(AiAssistantController.chat().url, {
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
        <AppShell title="AI Assistant" subtitle="Grounded financial Q&A">
            <Head title="AI assistant" />

            <Card className="mb-4">
                <CardContent className="flex flex-wrap items-center gap-2 p-4 text-sm text-muted">
                    <span className="flex items-center gap-2 font-medium text-foreground">
                        <InfoIcon size={15} className="text-info" />
                        Every statement is labeled by trust level — financial
                        numbers only ever come from tool results. The assistant
                        is read-only.
                    </span>
                    <span className="flex flex-wrap items-center gap-1.5">
                        <Badge variant="green">FACT</Badge>
                        <Badge variant="blue">CALCULATION</Badge>
                        <Badge variant="purple">AI INTERPRETATION</Badge>
                        <Badge variant="amber">WARNING</Badge>
                    </span>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Conversation</CardTitle>
                </CardHeader>
                <CardContent className="scroll-thin max-h-[58vh] space-y-5 overflow-y-auto p-4">
                    {messages.length === 0 ? (
                        <EmptyState
                            icon={<AiIcon size={20} />}
                            title="Ask about your financial data"
                            description="Examples: total budget for a fiscal year, search document content, compare a document with SIHANDAL, or validate a document."
                        />
                    ) : (
                        messages.map((message, index) =>
                            message.role === 'user' ? (
                                <div key={index} className="flex justify-end">
                                    <div className="max-w-[80%] rounded-lg rounded-br-sm bg-brand px-3 py-2 text-sm text-brand-foreground">
                                        {message.content}
                                    </div>
                                </div>
                            ) : (
                                <div
                                    key={index}
                                    className="max-w-[92%] space-y-3"
                                >
                                    {message.toolCalls !== undefined &&
                                    message.toolCalls.length > 0 ? (
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="text-xs text-subtle">
                                                Tools called:
                                            </span>
                                            {message.toolCalls.map(
                                                (tool, toolIndex) => (
                                                    <Badge
                                                        key={toolIndex}
                                                        variant="neutral"
                                                    >
                                                        <CpuIcon size={11} />
                                                        {tool.name}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    ) : null}

                                    {message.statements !== undefined ? (
                                        <p className="text-sm leading-relaxed text-foreground">
                                            {message.content}
                                        </p>
                                    ) : null}

                                    {(message.statements ?? []).map(
                                        (statement, statementIndex) => {
                                            const LabelIcon =
                                                LABEL_ICONS[statement.label];

                                            return (
                                                <div
                                                    key={statementIndex}
                                                    className="rounded-lg border border-border bg-elevated p-3"
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge
                                                            variant={
                                                                LABEL_VARIANTS[
                                                                    statement
                                                                        .label
                                                                ]
                                                            }
                                                        >
                                                            <LabelIcon
                                                                size={11}
                                                            />
                                                            {statement.label}
                                                        </Badge>
                                                        <span className="text-[11px] text-subtle">
                                                            {
                                                                LABEL_EXPLANATIONS[
                                                                    statement
                                                                        .label
                                                                ]
                                                            }
                                                        </span>
                                                    </div>
                                                    <p className="mt-1.5 text-sm text-foreground">
                                                        {statement.text}
                                                    </p>
                                                    {statement.source !==
                                                    null ? (
                                                        <p className="mt-1 text-xs text-subtle">
                                                            source:{' '}
                                                            {statement.source}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            );
                                        },
                                    )}

                                    {message.statements === undefined && (
                                        <div className="rounded-lg bg-elevated px-3 py-2 text-sm text-foreground">
                                            {message.content}
                                        </div>
                                    )}
                                </div>
                            ),
                        )
                    )}
                    {error !== null ? (
                        <p className="flex items-center gap-2 text-sm text-danger">
                            <AlertCircleIcon size={15} />
                            {error}
                        </p>
                    ) : null}
                    {pending ? (
                        <div className="flex items-center gap-2 text-sm text-info">
                            <AiIcon size={15} className="animate-pulse" />
                            Thinking…
                        </div>
                    ) : null}
                </CardContent>
                <form
                    onSubmit={send}
                    className="flex items-end gap-2 border-t border-border p-3"
                >
                    <div className="flex-1">
                        <Input
                            value={question}
                            onChange={(event) =>
                                setQuestion(event.target.value)
                            }
                            placeholder="Ask about budgets, documents, SIHANDAL comparisons, validation..."
                            disabled={pending}
                            maxLength={2000}
                            aria-label="Question"
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={pending || question.trim() === ''}
                    >
                        <SendIcon size={15} />
                        {pending ? 'Thinking…' : 'Ask'}
                    </Button>
                </form>
            </Card>
        </AppShell>
    );
}
