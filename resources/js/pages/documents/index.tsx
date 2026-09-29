import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import {
    Badge,
    Button,
    Card,
    CardContent,
    EmptyState,
    Input,
    Label,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type DocumentItem = {
    id: number;
    status: string;
    original_filename: string;
    file_size_human: string;
    page_count: number | null;
    fiscal_year: number | null;
    document_number: string | null;
    document_type?: { id: number; code: string; name: string } | null;
    opd?: { id: number; code: string; name: string } | null;
    uploader?: { id: number; name: string } | null;
    created_at: string;
};

type Props = InertiaPageProps & {
    documents: {
        data: DocumentItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        status: string;
        document_type_id: number | null;
        fiscal_year: number | null;
        search: string;
    };
    documentTypes: { id: number; code: string; name: string }[];
    auth: { can: { create: boolean } };
};

const STATUS_VARIANTS: Record<
    string,
    'neutral' | 'blue' | 'green' | 'amber' | 'red' | 'purple'
> = {
    uploaded: 'neutral',
    queued: 'blue',
    processing: 'blue',
    ocr: 'blue',
    ai_analysis: 'blue',
    extraction_complete: 'blue',
    validation: 'amber',
    review_required: 'amber',
    approved: 'green',
    synced: 'purple',
    completed: 'green',
    processing_failed: 'red',
    ocr_failed: 'red',
    ai_failed: 'red',
    validation_failed: 'red',
    sync_failed: 'red',
};

export default function DocumentsIndex({
    documents,
    filters,
    documentTypes,
    auth,
}: Props) {
    const form = useForm<{
        file: File | null;
        document_type_id: string;
        fiscal_year: string;
    }>({
        file: null,
        document_type_id: '',
        fiscal_year: String(new Date().getFullYear()),
    });

    function submitUpload(event: FormEvent) {
        event.preventDefault();
        form.post('/documents', {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <AppShell title="Documents">
            <Head title="Documents" />

            {auth.can.create ? (
                <Card className="mb-6">
                    <CardContent className="p-4">
                        <h2 className="mb-3 text-sm font-semibold text-neutral-900">
                            Upload document
                        </h2>
                        <form
                            onSubmit={submitUpload}
                            className="grid gap-3 md:grid-cols-[2fr_1fr_1fr_auto] md:items-end"
                        >
                            <div>
                                <Label htmlFor="file">
                                    File (PDF, image, DOCX, XLSX, CSV)
                                </Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".pdf,.png,.jpg,.jpeg,.webp,.tif,.tiff,.docx,.xlsx,.csv"
                                    onChange={(e) =>
                                        form.setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="h-auto py-1.5 file:mr-3 file:rounded file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm"
                                />
                                {form.errors.file ? (
                                    <p className="mt-1 text-xs text-red-600">
                                        {form.errors.file}
                                    </p>
                                ) : null}
                            </div>
                            <div>
                                <Label htmlFor="document_type_id">
                                    Document type
                                </Label>
                                <select
                                    id="document_type_id"
                                    className="h-10 w-full rounded-md border border-neutral-300 bg-white px-2 text-sm"
                                    value={form.data.document_type_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'document_type_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        Auto (classify later)
                                    </option>
                                    {documentTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="fiscal_year">Fiscal year</Label>
                                <Input
                                    id="fiscal_year"
                                    type="number"
                                    min={2000}
                                    max={2100}
                                    value={form.data.fiscal_year}
                                    onChange={(e) =>
                                        form.setData(
                                            'fiscal_year',
                                            e.target.value,
                                        )
                                    }
                                />
                                {form.errors.fiscal_year ? (
                                    <p className="mt-1 text-xs text-red-600">
                                        {form.errors.fiscal_year}
                                    </p>
                                ) : null}
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Uploading…' : 'Upload'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            ) : null}

            <Card>
                <CardContent className="p-4">
                    <form
                        className="mb-4 grid gap-3 md:grid-cols-4"
                        method="get"
                    >
                        <div className="md:col-span-2">
                            <Label htmlFor="search">Search</Label>
                            <Input
                                id="search"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Filename or document number…"
                            />
                        </div>
                        <div>
                            <Label htmlFor="filter_status">Status</Label>
                            <select
                                id="filter_status"
                                name="status"
                                defaultValue={filters.status}
                                className="h-10 w-full rounded-md border border-neutral-300 bg-white px-2 text-sm"
                            >
                                <option value="">All</option>
                                <option value="uploaded">Uploaded</option>
                                <option value="review_required">
                                    Needs review
                                </option>
                                <option value="approved">Approved</option>
                                <option value="synced">Synced</option>
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="filter_type">Type</Label>
                            <select
                                id="filter_type"
                                name="document_type_id"
                                defaultValue={filters.document_type_id ?? ''}
                                className="h-10 w-full rounded-md border border-neutral-300 bg-white px-2 text-sm"
                            >
                                <option value="">All</option>
                                {documentTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="md:col-span-4">
                            <Button type="submit" variant="outline" size="sm">
                                Apply filters
                            </Button>
                        </div>
                    </form>

                    {documents.data.length === 0 ? (
                        <EmptyState
                            title="No documents yet"
                            description="Uploaded financial documents appear here with their processing status. Duplicate uploads (identical file content) are rejected automatically."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-left text-xs tracking-wide text-neutral-500 uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            OPD
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Fiscal year
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Size
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-2 font-medium">
                                            Uploaded
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {documents.data.map((document) => (
                                        <tr
                                            key={document.id}
                                            className="border-b border-neutral-100 hover:bg-neutral-50"
                                        >
                                            <td className="py-2 pr-4">
                                                <Link
                                                    href={`/documents/${document.id}`}
                                                    className="font-medium text-blue-700 hover:underline"
                                                >
                                                    {document.original_filename}
                                                </Link>
                                                {document.document_number ? (
                                                    <p className="text-xs text-neutral-500">
                                                        {
                                                            document.document_number
                                                        }
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {document.document_type?.name ??
                                                    '—'}
                                            </td>
                                            <td className="py-2 pr-4">
                                                {document.opd?.name ?? '—'}
                                            </td>
                                            <td className="py-2 pr-4 tabular-nums">
                                                {document.fiscal_year ?? '—'}
                                            </td>
                                            <td className="py-2 pr-4 tabular-nums">
                                                {document.file_size_human}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant={
                                                        STATUS_VARIANTS[
                                                            document.status
                                                        ] ?? 'neutral'
                                                    }
                                                >
                                                    {document.status.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="py-2 text-xs text-neutral-500">
                                                {new Date(
                                                    document.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {documents.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between text-xs text-neutral-500">
                            <span>
                                Page {documents.current_page} of{' '}
                                {documents.last_page} · {documents.total}{' '}
                                documents
                            </span>
                            <div className="flex gap-1">
                                {documents.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            className={`rounded border px-2 py-1 ${
                                                link.active
                                                    ? 'border-blue-600 bg-blue-50 text-blue-700'
                                                    : 'border-neutral-200 hover:bg-neutral-50'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : null,
                                )}
                            </div>
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}
