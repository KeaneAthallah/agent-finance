import { Head } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import {
    CpuIcon,
    DocumentsIcon,
    InfoIcon,
    LayersIcon,
    QueueIcon,
    ScanIcon,
    SihandalIcon,
    ValidationIcon,
} from '@/components/icons';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    FieldRow,
    PageHeader,
} from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type Props = InertiaPageProps & {
    settings: {
        ai: {
            provider: string;
            default_model: string;
            embedding_model: string;
            key_configured: boolean;
            available_providers: string[];
        };
        ocr: { driver: string; language: string };
        sihandal: {
            base_url: string;
            timeout: number;
            cache_ttl: number;
        };
        validation: { low_confidence: number; review_confidence: number };
        documents: {
            disk: string;
            max_size_kb: number;
            allowed_extensions: string[];
        };
        queue: string;
    };
};

function Panel({
    icon,
    title,
    description,
    children,
}: {
    icon: React.ReactNode;
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2.5">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-elevated text-muted">
                        {icon}
                    </span>
                    <div>
                        <CardTitle>{title}</CardTitle>
                        {description ? (
                            <CardDescription>{description}</CardDescription>
                        ) : null}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="px-4 py-2">{children}</CardContent>
        </Card>
    );
}

export default function SettingsIndex({ settings }: Props) {
    return (
        <AppShell title="Settings" subtitle="Effective platform configuration">
            <Head title="Settings" />

            <PageHeader
                title="Settings"
                description="The effective platform configuration. Settings change by environment (config files and env variables) — this page makes them visible to platform staff; there is no write path here."
            />

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel
                    icon={<CpuIcon size={16} />}
                    title="AI layer"
                    description="Extraction and classification provider"
                >
                    <FieldRow label="Provider" value={settings.ai.provider} />
                    <FieldRow
                        label="Default model"
                        value={settings.ai.default_model}
                    />
                    <FieldRow
                        label="Embedding model"
                        value={settings.ai.embedding_model}
                    />
                    <FieldRow
                        label="API key"
                        value={
                            settings.ai.key_configured ? (
                                <span className="inline-flex items-center gap-1.5 text-success">
                                    <InfoIcon size={13} />
                                    configured
                                </span>
                            ) : (
                                'not set (fake provider)'
                            )
                        }
                    />
                    <FieldRow
                        label="Available providers"
                        value={settings.ai.available_providers.join(', ')}
                    />
                </Panel>

                <Panel
                    icon={<ScanIcon size={16} />}
                    title="OCR"
                    description="Page image text recognition"
                >
                    <FieldRow label="Driver" value={settings.ocr.driver} />
                    <FieldRow label="Languages" value={settings.ocr.language} />
                </Panel>

                <Panel
                    icon={<SihandalIcon size={16} />}
                    title="SIHANDAL"
                    description="System of record connection"
                >
                    <FieldRow
                        label="Base URL"
                        value={
                            <span className="font-mono text-xs break-all">
                                {settings.sihandal.base_url}
                            </span>
                        }
                    />
                    <FieldRow
                        label="Request timeout"
                        value={`${settings.sihandal.timeout}s`}
                    />
                    <FieldRow
                        label="Cache TTL"
                        value={`${settings.sihandal.cache_ttl}s`}
                    />
                </Panel>

                <Panel
                    icon={<ValidationIcon size={16} />}
                    title="Validation engine"
                    description="Thresholds that route documents to review"
                >
                    <FieldRow
                        label="Low confidence threshold"
                        value={String(settings.validation.low_confidence)}
                    />
                    <FieldRow
                        label="Review confidence threshold"
                        value={String(settings.validation.review_confidence)}
                    />
                </Panel>

                <Panel
                    icon={<DocumentsIcon size={16} />}
                    title="Documents"
                    description="Upload constraints and storage"
                >
                    <FieldRow
                        label="Storage disk"
                        value={settings.documents.disk}
                    />
                    <FieldRow
                        label="Max upload size"
                        value={`${settings.documents.max_size_kb} KB`}
                    />
                    <FieldRow
                        label="Allowed extensions"
                        value={settings.documents.allowed_extensions.join(', ')}
                    />
                </Panel>

                <Panel
                    icon={<QueueIcon size={16} />}
                    title="Infrastructure"
                    description="Queue and background processing"
                >
                    <FieldRow label="Queue connection" value={settings.queue} />
                </Panel>
            </div>

            <p className="mt-4 flex items-start gap-2 text-xs text-subtle">
                <LayersIcon size={14} className="mt-0.5 shrink-0" />
                Theme preference is stored in your browser and follows your
                operating system by default. Use the theme control in the header
                to switch between light, dark and system.
            </p>
        </AppShell>
    );
}
