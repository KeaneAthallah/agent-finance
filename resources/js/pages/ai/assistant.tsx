import { Head, Link } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import AiAssistantController from '@/actions/App/Http/Controllers/AiAssistantController';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    AlertCircleIcon,
    AiIcon,
    CheckCircleIcon,
    CheckIcon,
    CpuIcon,
    FileIcon,
    InfoIcon,
    RefreshIcon,
    SendIcon,
    SettingsIcon,
    XIcon,
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
import { Markdown } from '@/components/markdown';
import { createSseParser } from '@/lib/sse';
import type { InertiaPageProps } from '@/types';

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

type SourceRef = {
    type: string;
    id: number;
};

type Message = {
    role: 'user' | 'assistant';
    content: string;
    statements?: Statement[];
    toolCalls?: ToolCall[];
    sourceRefs?: SourceRef[];
};

type Draft = {
    content: string;
    toolCalls: ToolCall[];
    sourceRefs: SourceRef[];
};

type Conversation = {
    id: number;
    title: string;
    updated_at: string;
};

type Props = InertiaPageProps & {
    conversations: Conversation[];
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

const LABEL_ACCENTS: Record<StatementLabel, string> = {
    FACT: 'border-l-success',
    CALCULATION: 'border-l-info',
    'AI INTERPRETATION': 'border-l-purple',
    WARNING: 'border-l-warning',
};

const SUGGESTIONS = [
    'Berapa total anggaran TA 2026?',
    'Cari dokumen tentang honor kegiatan',
    'Bandingkan dokumen dengan SIHANDAL',
    'Validasi dokumen SPM terbaru',
];

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match !== null ? decodeURIComponent(match[1]) : '';
}

function requestHeaders(): HeadersInit {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}

async function errorMessage(response: Response, fallback: string): Promise<Error> {
    try {
        const body = (await response.json()) as { message?: unknown };

        if (typeof body.message === 'string' && body.message !== '') {
            return new Error(body.message);
        }
    } catch {
        /* non-JSON error body — fall back */
    }

    return new Error(fallback);
}

function isAbortError(exception: unknown): boolean {
    return exception instanceof DOMException && exception.name === 'AbortError';
}

function emptyDraft(): Draft {
    return { content: '', toolCalls: [], sourceRefs: [] };
}

export default function AiAssistant({ conversations: initialConversations }: Props) {
    const [conversations, setConversations] = useState<Conversation[]>(
        initialConversations,
    );
    const [activeId, setActiveId] = useState<number | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [question, setQuestion] = useState('');
    const [pending, setPending] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);

    const draftRef = useRef<Draft | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    function updateDraft(next: Draft | null) {
        draftRef.current = next;
        setDraft(next);
    }

    function openConversation(id: number) {
        setActiveId(id);
        setError(null);

        const cached = conversations.find((conversation) => conversation.id === id);

        if (cached === undefined) {
            return;
        }

        setLoading(true);

        fetch(AiAssistantController.messages(id).url, {
            headers: requestHeaders(),
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw await errorMessage(
                        response,
                        `Failed to load conversation (${response.status}).`,
                    );
                }

                const data = (await response.json()) as {
                    data: {
                        role: string;
                        content: string;
                        statements: Statement[] | null;
                        tool_calls: ToolCall[] | null;
                    }[];
                };

                setMessages(
                    data.data.map((entry) => ({
                        role: entry.role === 'user' ? 'user' : 'assistant',
                        content: entry.content,
                        statements: entry.statements ?? [],
                        toolCalls: entry.tool_calls ?? [],
                    })),
                );
                setLoading(false);
            })
            .catch(async (exception: unknown) => {
                setLoading(false);
                setError(
                    exception instanceof Error
                        ? exception.message
                        : 'Unable to load conversation.',
                );
            });
    }

    function newChat() {
        setActiveId(null);
        setMessages([]);
        setQuestion('');
        setError(null);
        updateDraft(null);
    }

    function renameConversation(conversation: Conversation) {
        const title = window.prompt('Nama percakapan:', conversation.title);

        if (title === null || title.trim() === '') {
            return;
        }

        fetch(AiAssistantController.rename(conversation.id).url, {
            method: 'PATCH',
            headers: requestHeaders(),
            body: JSON.stringify({ title: title.trim() }),
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw await errorMessage(
                        response,
                        `Failed to rename (${response.status}).`,
                    );
                }

                const data = (await response.json()) as { title: string };

                setConversations((previous) =>
                    previous.map((item) =>
                        item.id === conversation.id
                            ? { ...item, title: data.title }
                            : item,
                    ),
                );
            })
            .catch((exception: unknown) => {
                if (exception instanceof Error) {
                    setError(exception.message);
                }
            });
    }

    function deleteConversation(conversation: Conversation) {
        const confirmed = window.confirm(
            'Hapus percakapan ini beserta seluruh riwayatnya?',
        );

        if (!confirmed) {
            return;
        }

        fetch(AiAssistantController.destroy(conversation.id).url, {
            method: 'DELETE',
            headers: requestHeaders(),
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw await errorMessage(
                        response,
                        `Failed to delete (${response.status}).`,
                    );
                }

                setConversations((previous) =>
                    previous.filter((item) => item.id !== conversation.id),
                );

                if (activeId === conversation.id) {
                    newChat();
                }
            })
            .catch((exception: unknown) => {
                if (exception instanceof Error) {
                    setError(exception.message);
                }
            });
    }

    function copyMessage(content: string, index: number) {
        if (typeof navigator.clipboard === 'undefined') {
            return;
        }

        navigator.clipboard
            .writeText(content)
            .then(() => {
                setCopiedIndex(index);
                window.setTimeout(() => setCopiedIndex(null), 1500);
            })
            .catch(() => {
                /* clipboard denied */
            });
    }

    function stopStreaming() {
        abortRef.current?.abort();
    }

    async function send(event: FormEvent) {
        event.preventDefault();

        const trimmed = question.trim();

        if (trimmed === '' || pending) {
            return;
        }

        await ask(trimmed);
    }

    async function ask(trimmed: string) {
        const userMessage: Message = { role: 'user', content: trimmed };

        setMessages((previous) => [...previous, userMessage]);
        setQuestion('');
        setPending(true);
        setError(null);
        updateDraft(emptyDraft());

        const controller = new AbortController();

        abortRef.current = controller;

        try {
            const response = await fetch(AiAssistantController.stream().url, {
                method: 'POST',
                headers: {
                    ...requestHeaders(),
                    Accept: 'text/event-stream',
                },
                body: JSON.stringify({
                    question: trimmed,
                    conversation_id: activeId,
                }),
                signal: controller.signal,
            });

            if (!response.ok) {
                throw await errorMessage(
                    response,
                    `Assistant request failed (${response.status}).`,
                );
            }

            if (response.body === null) {
                throw new Error('Streaming is not supported by this browser.');
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            const parser = createSseParser();

            const handleEvent = (event: string, raw: string) => {
                if (raw === '') {
                    return;
                }

                const payload = JSON.parse(raw) as Record<string, unknown>;
                const current = draftRef.current ?? emptyDraft();

                if (event === 'tool') {
                    const name = payload.name;

                    if (typeof name === 'string') {
                        updateDraft({
                            ...current,
                            toolCalls: [
                                ...current.toolCalls,
                                {
                                    name:
                                        payload.error === true
                                            ? `${name} (failed)`
                                            : name,
                                    arguments: {},
                                },
                            ],
                        });
                    }

                    return;
                }

                if (event === 'delta') {
                    const delta = payload.delta;

                    if (typeof delta === 'string') {
                        updateDraft({ ...current, content: current.content + delta });
                    }

                    return;
                }

                if (event === 'error') {
                    const message = payload.message;

                    setMessages((previous) =>
                        previous.filter((entry) => entry !== userMessage),
                    );
                    updateDraft(null);
                    setError(
                        typeof message === 'string' && message !== ''
                            ? message
                            : 'Assistant unavailable.',
                    );

                    return;
                }

                if (event === 'done') {
                    const finished: Message = {
                        role: 'assistant',
                        content:
                            typeof payload.summary === 'string' ? payload.summary : '',
                        statements: Array.isArray(payload.statements)
                            ? (payload.statements as Statement[])
                            : [],
                        toolCalls: Array.isArray(payload.tool_calls)
                            ? (payload.tool_calls as ToolCall[])
                            : [],
                        sourceRefs: Array.isArray(
                            (payload.meta as { source_refs?: SourceRef[] } | undefined)
                                ?.source_refs,
                        )
                            ? (
                                  payload.meta as { source_refs: SourceRef[] }
                              ).source_refs
                            : [],
                    };

                    setMessages((previous) => [...previous, finished]);
                    updateDraft(null);

                    const conversationId = payload.conversation_id;

                    // First turn of a new conversation: start tracking it
                    // locally so follow-ups reuse the same server thread.
                    if (activeId === null && typeof conversationId === 'number') {
                        setActiveId(conversationId);
                        setConversations((previous) => [
                            {
                                id: conversationId,
                                title: trimmed.slice(0, 120),
                                updated_at: new Date().toISOString(),
                            },
                            ...previous,
                        ]);
                    }
                }
            };

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                for (const parsed of parser.push(
                    decoder.decode(value, { stream: true }),
                )) {
                    handleEvent(parsed.event, parsed.data);
                }
            }

            for (const parsed of parser.flush()) {
                handleEvent(parsed.event, parsed.data);
            }
        } catch (exception) {
            if (isAbortError(exception)) {
                // Keep whatever streamed in before the stop so the answer
                // the user started reading is not thrown away.
                const partial = draftRef.current;

                if (partial !== null && partial.content !== '') {
                    setMessages((previous) => [
                        ...previous,
                        {
                            role: 'assistant',
                            content: partial.content,
                            toolCalls: partial.toolCalls,
                            sourceRefs: partial.sourceRefs,
                        },
                    ]);
                }

                updateDraft(null);
            } else {
                setMessages((previous) =>
                    previous.filter((message) => message !== userMessage),
                );
                updateDraft(null);
                setError(
                    exception instanceof Error
                        ? exception.message
                        : 'Assistant unavailable.',
                );
            }
        } finally {
            abortRef.current = null;
            setPending(false);
            updateDraft(null);
        }
    }

    function renderCitations(refs: SourceRef[]) {
        const documents = refs.filter(
            (ref) => ref.type === 'document' && Number.isFinite(ref.id),
        );

        if (documents.length === 0) {
            return null;
        }

        return (
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="text-xs text-subtle">Sources:</span>
                {documents.map((ref) => (
                    <Link
                        key={ref.id}
                        href={DocumentController.show.url(ref.id)}
                        className="flex items-center gap-1 rounded-full border border-border bg-elevated px-2 py-0.5 text-xs text-foreground hover:border-brand hover:text-brand"
                    >
                        <FileIcon size={11} />
                        Document #{ref.id}
                    </Link>
                ))}
            </div>
        );
    }

    function renderToolChips(toolCalls: ToolCall[]) {
        if (toolCalls.length === 0) {
            return null;
        }

        return (
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="text-xs text-subtle">Tools called:</span>
                {toolCalls.map((tool, toolIndex) => (
                    <Badge key={toolIndex} variant="neutral">
                        <CpuIcon size={11} />
                        {tool.name}
                    </Badge>
                ))}
            </div>
        );
    }

    function renderCopyButton(content: string, index: number) {
        const copied = copiedIndex === index;

        return (
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => copyMessage(content, index)}
                    title="Copy answer"
                >
                    {copied ? <CheckIcon size={13} /> : null}
                    {copied ? 'Copied' : 'Copy'}
                </Button>
                {messages.length > 0 && index === messages.length - 1 && !pending ? (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                const lastUser = [...messages]
                                    .reverse()
                                    .find((entry) => entry.role === 'user');

                                if (lastUser !== undefined && !pending) {
                                    void ask(lastUser.content);
                                }
                            }}
                            title="Ask this again"
                        >
                            <RefreshIcon size={13} />
                            Regenerate
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                const lastUser = [...messages]
                                    .reverse()
                                    .find((entry) => entry.role === 'user');

                                if (lastUser !== undefined) {
                                    setQuestion(lastUser.content);
                                }
                            }}
                            title="Edit the last question before re-asking"
                        >
                            Edit
                        </Button>
                    </>
                ) : null}
            </div>
        );
    }

    return (
        <AppShell title="AI Assistant" subtitle="Grounded financial Q&A">
            <Head title="AI assistant" />

            <div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-info/25 bg-info-subtle/50 px-4 py-3 text-sm">
                <span className="flex items-center gap-2 font-medium text-foreground">
                    <InfoIcon size={15} className="text-info" />
                    Read-only · every statement is labeled by trust level ·
                    financial numbers only ever come from tool results.
                </span>
                <span className="flex flex-wrap items-center gap-1.5">
                    <Badge variant="green">FACT</Badge>
                    <Badge variant="blue">CALCULATION</Badge>
                    <Badge variant="purple">AI INTERPRETATION</Badge>
                    <Badge variant="amber">WARNING</Badge>
                </span>
            </div>

            <Card className="mb-4">
                <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                    <CardTitle>Conversations</CardTitle>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={newChat}
                    >
                        <RefreshIcon size={13} />
                        New chat
                    </Button>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2 p-4 pt-0">
                    {conversations.length === 0 ? (
                        <p className="text-sm text-subtle">
                            No conversations yet.
                        </p>
                    ) : (
                        conversations.map((conversation) => {
                            const active = conversation.id === activeId;

                            return (
                                <span
                                    key={conversation.id}
                                    className={`flex items-center gap-1.5 rounded-full border py-0.5 pr-1 pl-2.5 text-xs transition-colors ${
                                        active
                                            ? 'border-brand/40 bg-brand-subtle text-brand'
                                            : 'border-border bg-elevated text-foreground'
                                    }`}
                                >
                                    <button
                                        type="button"
                                        onClick={() =>
                                            openConversation(conversation.id)
                                        }
                                        className="max-w-[200px] cursor-pointer truncate"
                                        title={conversation.title}
                                    >
                                        {conversation.title}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            renameConversation(conversation)
                                        }
                                        aria-label="Rename"
                                        className="flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-subtle hover:bg-surface hover:text-foreground"
                                        title="Rename"
                                    >
                                        <SettingsIcon size={12} />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            deleteConversation(conversation)
                                        }
                                        aria-label="Delete"
                                        className="flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-subtle hover:bg-surface hover:text-danger"
                                        title="Delete"
                                    >
                                        <XIcon size={12} />
                                    </button>
                                </span>
                            );
                        })
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Conversation</CardTitle>
                </CardHeader>
                <CardContent className="scroll-thin max-h-[52vh] space-y-5 overflow-y-auto p-4">
                    {loading ? (
                        <div className="flex items-center gap-2 text-sm text-info">
                            <AiIcon size={15} className="animate-pulse" />
                            Loading conversation…
                        </div>
                    ) : messages.length === 0 && draft === null ? (
                        <div className="space-y-3">
                            <EmptyState
                                icon={<AiIcon size={20} />}
                                title="Ask about your financial data"
                                description="Examples: total budget for a fiscal year, search document content, compare a document with SIHANDAL, or validate a document."
                            />
                            <div className="flex flex-wrap justify-center gap-2">
                                {SUGGESTIONS.map((suggestion) => (
                                    <Button
                                        key={suggestion}
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setQuestion(suggestion)}
                                    >
                                        {suggestion}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <>
                            {messages.map((message, index) =>
                                message.role === 'user' ? (
                                    <div key={index} className="flex justify-end">
                                        <div className="max-w-[80%] rounded-2xl rounded-br-md bg-brand px-3.5 py-2.5 text-sm text-brand-foreground shadow-xs">
                                            {message.content}
                                        </div>
                                    </div>
                                ) : (
                                    <div key={index} className="flex gap-3">
                                        <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand to-purple text-brand-foreground shadow-xs">
                                            <AiIcon size={14} />
                                        </span>
                                        <div className="min-w-0 flex-1 space-y-3">
                                            {renderToolChips(
                                                message.toolCalls ?? [],
                                            )}

                                            <div className="rounded-xl border border-border bg-surface p-3.5 shadow-xs">
                                                <Markdown>
                                                    {message.content}
                                                </Markdown>
                                            </div>

                                            {(message.statements ?? []).map(
                                                (statement, statementIndex) => {
                                                    const LabelIcon =
                                                        LABEL_ICONS[
                                                            statement.label
                                                        ];

                                                    return (
                                                        <div
                                                            key={statementIndex}
                                                            className={`rounded-xl border border-l-2 border-border bg-elevated p-3.5 ${LABEL_ACCENTS[statement.label]}`}
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
                                                                        size={
                                                                            11
                                                                        }
                                                                    />
                                                                    {
                                                                        statement.label
                                                                    }
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
                                                                    {
                                                                        statement.source
                                                                    }
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                    );
                                                },
                                            )}

                                            {renderCitations(
                                                message.sourceRefs ?? [],
                                            )}

                                            <div className="flex justify-end">
                                                {renderCopyButton(
                                                    message.content,
                                                    index,
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ),
                            )}

                            {draft !== null ? (
                                <div className="flex gap-3">
                                    <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand to-purple text-brand-foreground shadow-xs">
                                        <AiIcon size={14} />
                                    </span>
                                    <div className="min-w-0 flex-1 space-y-3">
                                        {renderToolChips(draft.toolCalls)}
                                        <div className="rounded-xl border border-border bg-surface p-3.5 shadow-xs">
                                            {draft.content === '' ? (
                                                <p className="flex items-center gap-2 text-sm text-muted">
                                                    <AiIcon
                                                        size={14}
                                                        className="animate-pulse"
                                                    />
                                                    Thinking…
                                                </p>
                                            ) : (
                                                <Markdown>
                                                    {draft.content}
                                                </Markdown>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ) : null}
                        </>
                    )}
                    {error !== null ? (
                        <p className="flex items-center gap-2 text-sm text-danger">
                            <AlertCircleIcon size={15} />
                            {error}
                        </p>
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
                    {pending ? (
                        <Button type="button" variant="danger" onClick={stopStreaming}>
                            <XIcon size={15} />
                            Stop
                        </Button>
                    ) : (
                        <Button
                            type="submit"
                            disabled={question.trim() === ''}
                        >
                            <SendIcon size={15} />
                            Ask
                        </Button>
                    )}
                </form>
            </Card>
        </AppShell>
    );
}