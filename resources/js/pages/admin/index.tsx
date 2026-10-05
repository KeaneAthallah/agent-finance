import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AdminController from '@/actions/App/Http/Controllers/AdminController';
import { AppShell } from '@/components/layout/app-shell';
import {
    BuildingIcon,
    CpuIcon,
    DocumentsIcon,
    HeartPulseIcon,
    UsersIcon,
} from '@/components/icons';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    EmptyState,
    FieldRow,
    Input,
    Label,
    PageHeader,
    Select,
    Textarea,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: { id: number; slug: string; name: string } | null;
    opd: { id: number; code: string; name: string } | null;
    is_active: boolean;
};

type OpdRow = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type DocumentTypeRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
};

type PromptVersion = {
    id: number;
    name: string;
    version: string;
    system_prompt: string;
    model: string | null;
    temperature: string;
    is_active: boolean;
    created_at: string | null;
};

type Props = InertiaPageProps & {
    users: UserRow[];
    roles: { id: number; slug: string; name: string }[];
    opds: OpdRow[];
    documentTypes: DocumentTypeRow[];
    prompts: Record<string, PromptVersion[]>;
    health: Record<string, unknown>;
    can: { manage: boolean };
};

function Panel({
    icon,
    title,
    description,
    children,
    className,
}: {
    icon: React.ReactNode;
    title: string;
    description?: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center gap-2.5">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-elevated text-muted">
                        {icon}
                    </span>
                    <div className="min-w-0">
                        <CardTitle>{title}</CardTitle>
                        {description ? (
                            <CardDescription>{description}</CardDescription>
                        ) : null}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-4">{children}</CardContent>
        </Card>
    );
}

function ToggleButton({
    onClick,
    label,
    active,
}: {
    onClick: () => void;
    label: string;
    active: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="ml-2 cursor-pointer text-xs font-medium text-brand hover:underline"
            aria-label={`${active ? 'Deactivate' : 'Activate'} ${label}`}
        >
            {active ? 'deactivate' : 'activate'}
        </button>
    );
}

export default function AdminIndex({
    users,
    roles,
    opds,
    documentTypes,
    prompts,
    health,
    can,
}: Props) {
    const [opdForm, setOpdForm] = useState({ code: '', name: '' });
    const [promptForm, setPromptForm] = useState({
        name: 'financial_extraction',
        version: '',
        system_prompt: '',
        model: '',
        temperature: '0.10',
    });

    function saveUser(
        userId: number,
        fields: {
            role_id?: number | null;
            opd_id?: number | null;
            is_active?: boolean;
        },
    ) {
        router.post(AdminController.updateUser(userId).url, fields, {
            preserveScroll: true,
        });
    }

    function createOpd(event: FormEvent) {
        event.preventDefault();

        router.post(AdminController.storeOpd().url, opdForm, {
            preserveScroll: true,
            onSuccess: () => setOpdForm({ code: '', name: '' }),
        });
    }

    function saveOpd(
        opdId: number,
        fields: { name?: string; is_active?: boolean },
    ) {
        router.post(AdminController.updateOpd(opdId).url, fields, {
            preserveScroll: true,
        });
    }

    function saveDocumentType(typeId: number, fields: { is_active: boolean }) {
        router.post(AdminController.updateDocumentType(typeId).url, fields, {
            preserveScroll: true,
        });
    }

    function createPrompt(event: FormEvent) {
        event.preventDefault();

        router.post(
            AdminController.storePrompt().url,
            {
                name: promptForm.name,
                version: promptForm.version,
                system_prompt: promptForm.system_prompt,
                model: promptForm.model || null,
                temperature: Number.parseFloat(promptForm.temperature),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setPromptForm((form) => ({
                        ...form,
                        version: '',
                        system_prompt: '',
                    })),
            },
        );
    }

    function activatePrompt(promptId: number) {
        router.post(
            AdminController.activatePrompt(promptId).url,
            {},
            {
                preserveScroll: true,
            },
        );
    }

    const manage = can.manage;

    return (
        <AppShell title="Administration" subtitle="Platform control surface">
            <Head title="Administration" />

            <PageHeader
                title="Administration"
                description="Platform control surface. Page access is admin-only; mutations are super-admin-only and audit-logged. Prompt rows are immutable — editing creates a new version."
            />

            {/* Users */}
            <Panel
                icon={<UsersIcon size={16} />}
                title="Users"
                description="Role and OPD assignment — multi-tenancy is enforced server-side"
                className="mb-4"
            >
                <div className="scroll-thin overflow-x-auto">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr className="border-b border-border text-left text-xs tracking-wide text-subtle uppercase">
                                <th className="py-2 pr-4 font-medium">Name</th>
                                <th className="py-2 pr-4 font-medium">Role</th>
                                <th className="py-2 pr-4 font-medium">OPD</th>
                                <th className="py-2 font-medium">Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b border-border align-middle last:border-0"
                                >
                                    <td className="max-w-[240px] py-2 pr-4">
                                        <span className="block truncate font-medium text-foreground">
                                            {user.name}
                                        </span>
                                        <span className="block truncate text-xs text-subtle">
                                            {user.email}
                                        </span>
                                    </td>
                                    <td className="py-2 pr-4">
                                        {manage ? (
                                            <Select
                                                aria-label={`Role for ${user.name}`}
                                                className="h-8"
                                                value={user.role?.id ?? ''}
                                                onChange={(event) =>
                                                    saveUser(user.id, {
                                                        role_id:
                                                            event.target
                                                                .value === ''
                                                                ? null
                                                                : Number(
                                                                      event
                                                                          .target
                                                                          .value,
                                                                  ),
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    No role
                                                </option>
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.id}
                                                        value={role.id}
                                                    >
                                                        {role.name}
                                                    </option>
                                                ))}
                                            </Select>
                                        ) : (
                                            <span className="text-muted">
                                                {user.role?.name ?? '—'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="max-w-[260px] py-2 pr-4">
                                        {manage ? (
                                            <Select
                                                aria-label={`OPD for ${user.name}`}
                                                className="h-8"
                                                value={user.opd?.id ?? ''}
                                                onChange={(event) =>
                                                    saveUser(user.id, {
                                                        opd_id:
                                                            event.target
                                                                .value === ''
                                                                ? null
                                                                : Number(
                                                                      event
                                                                          .target
                                                                          .value,
                                                                  ),
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    Platform-wide
                                                </option>
                                                {opds.map((opd) => (
                                                    <option
                                                        key={opd.id}
                                                        value={opd.id}
                                                    >
                                                        {opd.code} — {opd.name}
                                                    </option>
                                                ))}
                                            </Select>
                                        ) : (
                                            <span className="truncate text-muted">
                                                {user.opd
                                                    ? `${user.opd.code} — ${user.opd.name}`
                                                    : 'Platform-wide'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-2">
                                        <Badge
                                            variant={
                                                user.is_active
                                                    ? 'green'
                                                    : 'neutral'
                                            }
                                        >
                                            {user.is_active
                                                ? 'active'
                                                : 'inactive'}
                                        </Badge>
                                        {manage ? (
                                            <ToggleButton
                                                label={user.name}
                                                active={user.is_active}
                                                onClick={() =>
                                                    saveUser(user.id, {
                                                        is_active:
                                                            !user.is_active,
                                                    })
                                                }
                                            />
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>

            <div className="mb-4 grid gap-4 lg:grid-cols-2">
                {/* OPDs */}
                <Panel
                    icon={<BuildingIcon size={16} />}
                    title="OPDs"
                    description="Organisational units — the tenancy boundary"
                >
                    <div className="scroll-thin overflow-x-auto">
                        <table className="w-full min-w-[420px] text-sm">
                            <tbody>
                                {opds.map((opd) => (
                                    <tr
                                        key={opd.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="max-w-[60%] py-1.5 pr-4">
                                            <span className="font-medium text-foreground">
                                                {opd.code}
                                            </span>
                                            <span className="ml-2 truncate text-muted">
                                                {opd.name}
                                            </span>
                                        </td>
                                        <td className="py-1.5 text-right">
                                            <Badge
                                                variant={
                                                    opd.is_active
                                                        ? 'green'
                                                        : 'neutral'
                                                }
                                            >
                                                {opd.is_active
                                                    ? 'active'
                                                    : 'inactive'}
                                            </Badge>
                                            {manage ? (
                                                <ToggleButton
                                                    label={opd.name}
                                                    active={opd.is_active}
                                                    onClick={() =>
                                                        saveOpd(opd.id, {
                                                            is_active:
                                                                !opd.is_active,
                                                        })
                                                    }
                                                />
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {manage ? (
                        <form
                            className="mt-4 rounded-md border border-border bg-elevated p-3"
                            onSubmit={createOpd}
                        >
                            <div className="mb-2 text-sm font-medium text-foreground">
                                New OPD
                            </div>
                            <div className="mb-2 grid grid-cols-3 gap-2">
                                <Input
                                    placeholder="Code (4.01.0)"
                                    value={opdForm.code}
                                    onChange={(event) =>
                                        setOpdForm({
                                            ...opdForm,
                                            code: event.target.value,
                                        })
                                    }
                                />
                                <Input
                                    className="col-span-2"
                                    placeholder="Name"
                                    value={opdForm.name}
                                    onChange={(event) =>
                                        setOpdForm({
                                            ...opdForm,
                                            name: event.target.value,
                                        })
                                    }
                                />
                            </div>
                            <Button size="sm" type="submit">
                                Create OPD
                            </Button>
                        </form>
                    ) : null}
                </Panel>

                {/* Document types */}
                <Panel
                    icon={<DocumentsIcon size={16} />}
                    title="Document types"
                    description="Schemas are code-owned; the catalog row is admin data — activation controls what the classifier may return"
                >
                    {documentTypes.length === 0 ? (
                        <EmptyState title="No document types" />
                    ) : (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[420px] text-sm">
                                <tbody>
                                    {documentTypes.map((type) => (
                                        <tr
                                            key={type.id}
                                            className="border-b border-border last:border-0"
                                        >
                                            <td className="max-w-[65%] py-1.5 pr-4">
                                                <span className="font-medium text-foreground uppercase">
                                                    {type.code}
                                                </span>
                                                <span className="ml-2 text-muted">
                                                    {type.name}
                                                </span>
                                                {type.description ? (
                                                    <span className="mt-0.5 block text-xs text-subtle">
                                                        {type.description}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="py-1.5 text-right">
                                                <Badge
                                                    variant={
                                                        type.is_active
                                                            ? 'green'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {type.is_active
                                                        ? 'active'
                                                        : 'inactive'}
                                                </Badge>
                                                {manage ? (
                                                    <ToggleButton
                                                        label={type.name}
                                                        active={type.is_active}
                                                        onClick={() =>
                                                            saveDocumentType(
                                                                type.id,
                                                                {
                                                                    is_active:
                                                                        !type.is_active,
                                                                },
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>

            {/* AI prompts */}
            <Panel
                icon={<CpuIcon size={16} />}
                title="AI prompts"
                description="Versioned and immutable: a change creates a new version row; activating a version moves the is_active flag — existing rows are never rewritten"
                className="mb-4"
            >
                {Object.keys(prompts).length === 0 ? (
                    <EmptyState
                        title="No prompts registered"
                        description="The pipeline falls back to built-in defaults until a prompt version is created."
                    />
                ) : (
                    Object.entries(prompts).map(([name, versions]) => (
                        <div key={name} className="mb-5 last:mb-0">
                            <h3 className="mb-2 text-sm font-medium text-foreground">
                                {name}
                            </h3>
                            <div className="scroll-thin overflow-x-auto">
                                <table className="w-full min-w-[560px] text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-xs tracking-wide text-subtle uppercase">
                                            <th className="py-1.5 pr-4 font-medium">
                                                Version
                                            </th>
                                            <th className="py-1.5 pr-4 font-medium">
                                                Model
                                            </th>
                                            <th className="py-1.5 pr-4 font-medium">
                                                Temp
                                            </th>
                                            <th className="hidden py-1.5 pr-4 font-medium md:table-cell">
                                                Created
                                            </th>
                                            <th className="py-1.5 font-medium">
                                                State
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {versions.map((version) => (
                                            <tr
                                                key={version.id}
                                                className="border-b border-border last:border-0"
                                            >
                                                <td className="py-1.5 pr-4 font-medium text-foreground">
                                                    {version.version}
                                                </td>
                                                <td className="py-1.5 pr-4 text-muted">
                                                    {version.model ?? '—'}
                                                </td>
                                                <td className="py-1.5 pr-4 text-muted tabular-nums">
                                                    {version.temperature}
                                                </td>
                                                <td className="hidden py-1.5 pr-4 text-muted tabular-nums md:table-cell">
                                                    {version.created_at
                                                        ? new Date(
                                                              version.created_at,
                                                          ).toLocaleDateString(
                                                              'id-ID',
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="py-1.5">
                                                    {version.is_active ? (
                                                        <Badge variant="green">
                                                            active
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="neutral">
                                                            inactive
                                                        </Badge>
                                                    )}
                                                    {manage &&
                                                    !version.is_active ? (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                activatePrompt(
                                                                    version.id,
                                                                )
                                                            }
                                                            className="ml-2 cursor-pointer text-xs font-medium text-brand hover:underline"
                                                        >
                                                            activate
                                                        </button>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))
                )}

                {manage ? (
                    <form
                        className="rounded-md border border-border bg-elevated p-3"
                        onSubmit={createPrompt}
                    >
                        <div className="mb-2 text-sm font-medium text-foreground">
                            New prompt version
                        </div>
                        <div className="mb-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <Input
                                placeholder="Name"
                                value={promptForm.name}
                                onChange={(event) =>
                                    setPromptForm({
                                        ...promptForm,
                                        name: event.target.value,
                                    })
                                }
                            />
                            <Input
                                placeholder="Version (e.g. 2.0)"
                                value={promptForm.version}
                                onChange={(event) =>
                                    setPromptForm({
                                        ...promptForm,
                                        version: event.target.value,
                                    })
                                }
                            />
                            <Input
                                placeholder="Model (optional)"
                                value={promptForm.model}
                                onChange={(event) =>
                                    setPromptForm({
                                        ...promptForm,
                                        model: event.target.value,
                                    })
                                }
                            />
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                max="2"
                                placeholder="Temperature"
                                value={promptForm.temperature}
                                onChange={(event) =>
                                    setPromptForm({
                                        ...promptForm,
                                        temperature: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <Label className="mb-1 block text-xs font-medium text-muted">
                            System prompt
                        </Label>
                        <Textarea
                            rows={5}
                            placeholder="Strict system prompt wrapping untrusted document text…"
                            value={promptForm.system_prompt}
                            onChange={(event) =>
                                setPromptForm({
                                    ...promptForm,
                                    system_prompt: event.target.value,
                                })
                            }
                        />
                        <Button size="sm" type="submit" className="mt-2">
                            Create version
                        </Button>
                    </form>
                ) : null}
            </Panel>

            {/* System health */}
            <Panel
                icon={<HeartPulseIcon size={16} />}
                title="System health"
                description="Live platform checks"
            >
                <div className="grid gap-x-8 gap-y-1 md:grid-cols-2">
                    {Object.entries(health).map(([key, value]) => (
                        <FieldRow
                            key={key}
                            label={key.replace(/_/g, ' ')}
                            value={
                                typeof value === 'boolean' ? (
                                    <Badge variant={value ? 'green' : 'red'}>
                                        {value ? 'yes' : 'no'}
                                    </Badge>
                                ) : (
                                    String(value)
                                )
                            }
                        />
                    ))}
                </div>
            </Panel>
        </AppShell>
    );
}
