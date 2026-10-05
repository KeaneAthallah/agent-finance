import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import { AppShell } from '@/components/layout/app-shell';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    BuildingIcon,
    DocumentsIcon,
    FileIcon,
    FilterIcon,
    SearchIcon,
    UploadIcon,
} from '@/components/icons';
import {
    Button,
    Card,
    CardContent,
    EmptyState,
    Input,
    Label,
    PageHeader,
    PaginationLinks,
    Select,
    StatusBadge,
    ViewSwitcher,
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
    can: { create: boolean };
};

type SortKey = 'newest' | 'oldest' | 'name';

const VIEW_KEY = 'agent-finance.documents-view';

function storedView(): 'table' | 'cards' {
    try {
        return window.localStorage?.getItem(VIEW_KEY) === 'cards'
            ? 'cards'
            : 'table';
    } catch {
        return 'table';
    }
}

export default function DocumentsIndex({
    documents,
    filters,
    documentTypes,
    can,
}: Props) {
    const [view, setView] = useState<'table' | 'cards'>(storedView);
    const [sort, setSort] = useState<SortKey>('newest');

    const uploadForm = useForm<{
        file: File | null;
        document_type_id: string;
        fiscal_year: string;
    }>({
        file: null,
        document_type_id: '',
        fiscal_year: String(new Date().getFullYear()),
    });

    const filterForm = useForm<{
        search: string;
        status: string;
        document_type_id: string;
        fiscal_year: string;
    }>({
        search: filters.search,
        status: filters.status,
        document_type_id: String(filters.document_type_id ?? ''),
        fiscal_year: String(filters.fiscal_year ?? ''),
    });

    function submitUpload(event: FormEvent) {
        event.preventDefault();

        uploadForm.post(DocumentController.store().url, {
            forceFormData: true,
            onSuccess: () => uploadForm.reset(),
        });
    }

    function applyFilters(event: FormEvent) {
        event.preventDefault();

        filterForm.get(DocumentController.index.url(), {
            preserveState: true,
        });
    }

    function resetFilters() {
        filterForm.setData({
            search: '',
            status: '',
            document_type_id: '',
            fiscal_year: '',
        });

        filterForm.get(DocumentController.index.url(), {
            preserveState: true,
        });
    }

    function changeView(next: 'table' | 'cards') {
        setView(next);

        try {
            window.localStorage?.setItem(VIEW_KEY, next);
        } catch {
            /* preference is best-effort */
        }
    }

    const rows = useMemo(() => {
        const sorted = [...documents.data];

        if (sort === 'newest') {
            sorted.sort(
                (a, b) =>
                    new Date(b.created_at).getTime() -
                    new Date(a.created_at).getTime(),
            );
        } else if (sort === 'oldest') {
            sorted.sort(
                (a, b) =>
                    new Date(a.created_at).getTime() -
                    new Date(b.created_at).getTime(),
            );
        } else {
            sorted.sort((a, b) =>
                a.original_filename.localeCompare(b.original_filename),
            );
        }

        return sorted;
    }, [documents.data, sort]);

    const hasActiveFilters =
        filterForm.data.search !== '' ||
        filterForm.data.status !== '' ||
        filterForm.data.document_type_id !== '' ||
        filterForm.data.fiscal_year !== '';

    return (
        <AppShell
            title="Documents"
            subtitle="Upload, track and open every financial document"
        >
            <Head title="Documents" />

            <PageHeader
                title="Documents"
                description="Every financial document, its processing state and its AI extraction — from upload to SIHANDAL sync."
                actions={
                    can.create ? (
                        <Button
                            onClick={() =>
                                document
                                    .getElementById('upload-document')
                                    ?.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'center',
                                    })
                            }
                        >
                            <UploadIcon size={15} />
                            Upload document
                        </Button>
                    ) : undefined
                }
            />

            {/* Upload */}
            {can.create ? (
                <Card id="upload-document" className="mb-5">
                    <CardContent className="p-4">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                            <UploadIcon size={15} />
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
                                        uploadForm.setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="h-auto py-1.5 file:mr-3 file:rounded file:border-0 file:bg-elevated file:px-3 file:py-1.5 file:text-sm"
                                />
                                {uploadForm.errors.file ? (
                                    <p className="mt-1 text-xs text-danger">
                                        {uploadForm.errors.file}
                                    </p>
                                ) : null}
                            </div>
                            <div>
                                <Label htmlFor="document_type_id">
                                    Document type
                                </Label>
                                <Select
                                    id="document_type_id"
                                    value={uploadForm.data.document_type_id}
                                    onChange={(e) =>
                                        uploadForm.setData(
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
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="fiscal_year">Fiscal year</Label>
                                <Input
                                    id="fiscal_year"
                                    type="number"
                                    min={2000}
                                    max={2100}
                                    value={uploadForm.data.fiscal_year}
                                    onChange={(e) =>
                                        uploadForm.setData(
                                            'fiscal_year',
                                            e.target.value,
                                        )
                                    }
                                />
                                {uploadForm.errors.fiscal_year ? (
                                    <p className="mt-1 text-xs text-danger">
                                        {uploadForm.errors.fiscal_year}
                                    </p>
                                ) : null}
                            </div>
                            <Button
                                type="submit"
                                disabled={uploadForm.processing}
                            >
                                {uploadForm.processing
                                    ? 'Uploading…'
                                    : 'Upload'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            ) : null}

            {/* Filters */}
            <Card className="mb-5">
                <CardContent className="p-4">
                    <form
                        onSubmit={applyFilters}
                        className="grid items-end gap-3 md:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto_auto]"
                    >
                        <div>
                            <Label htmlFor="filter-search">Search</Label>
                            <div className="relative">
                                <SearchIcon
                                    size={15}
                                    className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-subtle"
                                />
                                <Input
                                    id="filter-search"
                                    value={filterForm.data.search}
                                    onChange={(e) =>
                                        filterForm.setData(
                                            'search',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Filename or document number…"
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="filter-status">Status</Label>
                            <Select
                                id="filter-status"
                                value={filterForm.data.status}
                                onChange={(e) =>
                                    filterForm.setData('status', e.target.value)
                                }
                            >
                                <option value="">All statuses</option>
                                <option value="uploaded">Uploaded</option>
                                <option value="queued">Queued</option>
                                <option value="processing">Processing</option>
                                <option value="review_required">
                                    Needs review
                                </option>
                                <option value="approved">Approved</option>
                                <option value="synced">Synced</option>
                                <option value="completed">Completed</option>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="filter-type">Type</Label>
                            <Select
                                id="filter-type"
                                value={filterForm.data.document_type_id}
                                onChange={(e) =>
                                    filterForm.setData(
                                        'document_type_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">All types</option>
                                {documentTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="filter-year">Fiscal year</Label>
                            <Input
                                id="filter-year"
                                type="number"
                                min={2000}
                                max={2100}
                                placeholder="Any"
                                value={filterForm.data.fiscal_year}
                                onChange={(e) =>
                                    filterForm.setData(
                                        'fiscal_year',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" variant="outline" size="sm">
                                <FilterIcon size={14} />
                                Apply
                            </Button>
                            {hasActiveFilters ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>
                            ) : null}
                        </div>
                        <div className="md:col-span-2 lg:col-span-1">
                            <Label htmlFor="sort">Sort</Label>
                            <Select
                                id="sort"
                                value={sort}
                                onChange={(e) =>
                                    setSort(e.target.value as SortKey)
                                }
                            >
                                <option value="newest">Newest first</option>
                                <option value="oldest">Oldest first</option>
                                <option value="name">Name A–Z</option>
                            </Select>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {/* Results */}
            <Card>
                <CardContent className="p-4">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <p className="text-sm text-muted tabular-nums">
                            {documents.total} document
                            {documents.total === 1 ? '' : 's'}
                            {hasActiveFilters ? ' (filtered)' : ''}
                        </p>
                        <ViewSwitcher view={view} onChange={changeView} />
                    </div>

                    {rows.length === 0 ? (
                        <EmptyState
                            icon={<DocumentsIcon size={20} />}
                            title="No documents found"
                            description={
                                hasActiveFilters
                                    ? 'No documents match these filters. Try widening the search or resetting the filters.'
                                    : 'Uploaded financial documents appear here with their processing status. Duplicate uploads (identical file content) are rejected automatically.'
                            }
                            action={
                                hasActiveFilters ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={resetFilters}
                                    >
                                        Reset filters
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : view === 'table' ? (
                        <div className="scroll-thin overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs tracking-wide text-subtle uppercase">
                                        <th className="py-2 pr-4 font-medium">
                                            Document
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Type
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            OPD
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium md:table-cell">
                                            Fiscal year
                                        </th>
                                        <th className="hidden py-2 pr-4 font-medium lg:table-cell">
                                            Size
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="hidden py-2 font-medium sm:table-cell">
                                            Uploaded
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((document) => (
                                        <tr
                                            key={document.id}
                                            className="border-b border-border last:border-0 hover:bg-elevated"
                                        >
                                            <td className="max-w-[280px] py-2 pr-4">
                                                <Link
                                                    href={DocumentController.show.url(
                                                        {
                                                            document:
                                                                document.id,
                                                        },
                                                    )}
                                                    className="block truncate font-medium text-brand hover:underline"
                                                    title={
                                                        document.original_filename
                                                    }
                                                >
                                                    {document.original_filename}
                                                </Link>
                                                {document.document_number ? (
                                                    <p className="truncate text-xs text-subtle">
                                                        {
                                                            document.document_number
                                                        }
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <span className="inline-flex items-center gap-1.5 text-muted">
                                                    <FileIcon size={13} />
                                                    <span className="truncate">
                                                        {document.document_type
                                                            ?.name ?? '—'}
                                                    </span>
                                                </span>
                                            </td>
                                            <td className="max-w-[160px] py-2 pr-4">
                                                <span className="inline-flex items-center gap-1.5 text-muted">
                                                    <BuildingIcon size={13} />
                                                    <span className="truncate">
                                                        {document.opd?.name ??
                                                            '—'}
                                                    </span>
                                                </span>
                                            </td>
                                            <td className="hidden py-2 pr-4 text-muted tabular-nums md:table-cell">
                                                {document.fiscal_year ?? '—'}
                                            </td>
                                            <td className="hidden py-2 pr-4 text-muted tabular-nums lg:table-cell">
                                                {document.file_size_human}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <StatusBadge
                                                    status={document.status}
                                                />
                                            </td>
                                            <td className="hidden py-2 text-xs text-subtle sm:table-cell">
                                                {new Date(
                                                    document.created_at,
                                                ).toLocaleDateString('id-ID')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {rows.map((document) => (
                                <li key={document.id}>
                                    <Link
                                        href={DocumentController.show.url({
                                            document: document.id,
                                        })}
                                        className="block h-full rounded-lg border border-border bg-surface p-4 transition-colors hover:border-brand/40 hover:bg-elevated"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <p
                                                className="truncate text-sm font-medium text-foreground"
                                                title={
                                                    document.original_filename
                                                }
                                            >
                                                {document.original_filename}
                                            </p>
                                            <StatusBadge
                                                status={document.status}
                                            />
                                        </div>
                                        {document.document_number ? (
                                            <p className="mt-0.5 truncate text-xs text-subtle">
                                                {document.document_number}
                                            </p>
                                        ) : null}
                                        <dl className="mt-3 space-y-1 text-xs text-muted">
                                            <div className="flex items-center justify-between gap-2">
                                                <dt className="flex items-center gap-1.5">
                                                    <FileIcon size={12} />
                                                    Type
                                                </dt>
                                                <dd className="truncate font-medium text-foreground">
                                                    {document.document_type
                                                        ?.name ?? '—'}
                                                </dd>
                                            </div>
                                            <div className="flex items-center justify-between gap-2">
                                                <dt className="flex items-center gap-1.5">
                                                    <BuildingIcon size={12} />
                                                    OPD
                                                </dt>
                                                <dd className="truncate font-medium text-foreground">
                                                    {document.opd?.name ?? '—'}
                                                </dd>
                                            </div>
                                            <div className="flex items-center justify-between gap-2">
                                                <dt>Uploaded</dt>
                                                <dd className="font-medium text-foreground tabular-nums">
                                                    {new Date(
                                                        document.created_at,
                                                    ).toLocaleDateString(
                                                        'id-ID',
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {documents.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between gap-3">
                            <p className="text-xs text-subtle tabular-nums">
                                Page {documents.current_page} of{' '}
                                {documents.last_page}
                            </p>
                            <PaginationLinks links={documents.links} />
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </AppShell>
    );
}
