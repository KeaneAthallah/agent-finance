import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AdminController from '@/actions/App/Http/Controllers/AdminController';
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
    Label,
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
    auth: { can: { manage: boolean } };
};

export default function AdminIndex({
    users,
    roles,
    opds,
    documentTypes,
    prompts,
    health,
    auth,
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

    const manage = auth.can.manage;

    return (
        <AppShell title="Administration">
            <Head title="Administration" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                Platform control surface. Page access is admin-only; mutations
                are super-admin-only and audit-logged. Prompt rows are immutable
                — editing creates a new version (§33).
            </p>

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>Users</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                    <th className="py-2 pr-4 font-medium">
                                        Name
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Role
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        OPD
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Active
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b border-neutral-100 align-middle"
                                    >
                                        <td className="py-2 pr-4">
                                            <span className="font-medium text-neutral-900">
                                                {user.name}
                                            </span>
                                            <span className="mt-0.5 block text-xs text-neutral-500">
                                                {user.email}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4">
                                            {manage ? (
                                                <select
                                                    className="h-8 rounded-md border border-neutral-300 bg-white px-2 text-sm"
                                                    value={user.role?.id ?? ''}
                                                    onChange={(event) =>
                                                        saveUser(user.id, {
                                                            role_id:
                                                                event.target
                                                                    .value ===
                                                                ''
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
                                                </select>
                                            ) : (
                                                <span>
                                                    {user.role?.name ?? '—'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {manage ? (
                                                <select
                                                    className="h-8 rounded-md border border-neutral-300 bg-white px-2 text-sm"
                                                    value={user.opd?.id ?? ''}
                                                    onChange={(event) =>
                                                        saveUser(user.id, {
                                                            opd_id:
                                                                event.target
                                                                    .value ===
                                                                ''
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
                                                            {opd.code} —{' '}
                                                            {opd.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <span>
                                                    {user.opd
                                                        ? `${user.opd.code} — ${user.opd.name}`
                                                        : 'Platform-wide'}
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">
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
                                            {manage && (
                                                <button
                                                    className="ml-2 text-xs text-blue-700 hover:underline"
                                                    onClick={() =>
                                                        saveUser(user.id, {
                                                            is_active:
                                                                !user.is_active,
                                                        })
                                                    }
                                                >
                                                    toggle
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div className="mb-4 grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>OPDs</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <table className="mb-4 w-full text-sm">
                            <tbody>
                                {opds.map((opd) => (
                                    <tr
                                        key={opd.id}
                                        className="border-b border-neutral-100"
                                    >
                                        <td className="py-1.5">
                                            <span className="font-medium text-neutral-900">
                                                {opd.code}
                                            </span>
                                            <span className="ml-2 text-neutral-600">
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
                                            {manage && (
                                                <button
                                                    className="ml-2 text-xs text-blue-700 hover:underline"
                                                    onClick={() =>
                                                        saveOpd(opd.id, {
                                                            is_active:
                                                                !opd.is_active,
                                                        })
                                                    }
                                                >
                                                    toggle
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {manage && (
                            <form
                                className="rounded-md border border-neutral-200 p-3"
                                onSubmit={createOpd}
                            >
                                <div className="mb-2 text-sm font-medium text-neutral-700">
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
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Document types</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <p className="mb-3 text-xs text-neutral-500">
                            Schemas are code-owned (§6); the catalog row is
                            admin data — activation controls what the classifier
                            may return.
                        </p>
                        {documentTypes.length === 0 ? (
                            <EmptyState title="No document types" />
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {documentTypes.map((type) => (
                                        <tr
                                            key={type.id}
                                            className="border-b border-neutral-100"
                                        >
                                            <td className="py-1.5">
                                                <span className="font-medium text-neutral-900 uppercase">
                                                    {type.code}
                                                </span>
                                                <span className="ml-2 text-neutral-600">
                                                    {type.name}
                                                </span>
                                                {type.description && (
                                                    <span className="mt-0.5 block text-xs text-neutral-500">
                                                        {type.description}
                                                    </span>
                                                )}
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
                                                {manage && (
                                                    <button
                                                        className="ml-2 text-xs text-blue-700 hover:underline"
                                                        onClick={() =>
                                                            saveDocumentType(
                                                                type.id,
                                                                {
                                                                    is_active:
                                                                        !type.is_active,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        toggle
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>AI prompts</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    <p className="mb-3 text-xs text-neutral-500">
                        Versioned and immutable: a change creates a new version
                        row; activating a version moves the is_active flag —
                        existing rows are never rewritten (§33).
                    </p>

                    {Object.keys(prompts).length === 0 ? (
                        <EmptyState
                            title="No prompts registered"
                            description="The pipeline falls back to built-in defaults until a prompt version is created."
                        />
                    ) : (
                        Object.entries(prompts).map(([name, versions]) => (
                            <div key={name} className="mb-4 last:mb-0">
                                <h3 className="mb-2 text-sm font-medium text-neutral-700">
                                    {name}
                                </h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                                <th className="py-1.5 pr-4 font-medium">
                                                    Version
                                                </th>
                                                <th className="py-1.5 pr-4 font-medium">
                                                    Model
                                                </th>
                                                <th className="py-1.5 pr-4 font-medium">
                                                    Temp
                                                </th>
                                                <th className="py-1.5 pr-4 font-medium">
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
                                                    className="border-b border-neutral-100"
                                                >
                                                    <td className="py-1.5 pr-4 font-medium text-neutral-900">
                                                        {version.version}
                                                    </td>
                                                    <td className="py-1.5 pr-4 text-neutral-600">
                                                        {version.model ?? '—'}
                                                    </td>
                                                    <td className="py-1.5 pr-4 text-neutral-600">
                                                        {version.temperature}
                                                    </td>
                                                    <td className="py-1.5 pr-4 text-neutral-600">
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
                                                            !version.is_active && (
                                                                <button
                                                                    className="ml-2 text-xs text-blue-700 hover:underline"
                                                                    onClick={() =>
                                                                        activatePrompt(
                                                                            version.id,
                                                                        )
                                                                    }
                                                                >
                                                                    activate
                                                                </button>
                                                            )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))
                    )}

                    {manage && (
                        <form
                            className="mt-4 rounded-md border border-neutral-200 p-3"
                            onSubmit={createPrompt}
                        >
                            <div className="mb-2 text-sm font-medium text-neutral-700">
                                New prompt version
                            </div>
                            <div className="mb-2 grid grid-cols-4 gap-2">
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
                            <Label className="mb-1 block text-xs font-medium text-neutral-500">
                                System prompt
                            </Label>
                            <textarea
                                className="mb-2 h-24 w-full rounded-md border border-neutral-300 p-2 text-sm"
                                placeholder="Strict system prompt wrapping untrusted document text (§55)…"
                                value={promptForm.system_prompt}
                                onChange={(event) =>
                                    setPromptForm({
                                        ...promptForm,
                                        system_prompt: event.target.value,
                                    })
                                }
                            />
                            <Button size="sm" type="submit">
                                Create version
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>System health</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    <div className="grid gap-x-8 gap-y-1 md:grid-cols-2">
                        {Object.entries(health).map(([key, value]) => (
                            <div
                                key={key}
                                className="flex items-center justify-between gap-4 border-b border-neutral-100 py-2"
                            >
                                <span className="text-sm text-neutral-500">
                                    {key.replace(/_/g, ' ')}
                                </span>
                                <span className="text-sm font-medium text-neutral-900">
                                    {typeof value === 'boolean'
                                        ? value
                                            ? 'yes'
                                            : 'no'
                                        : String(value)}
                                </span>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </AppShell>
    );
}
