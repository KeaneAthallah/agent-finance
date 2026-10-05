import { Head } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
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

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-neutral-100 py-2 last:border-0">
            <span className="text-sm text-neutral-500">{label}</span>
            <span className="max-w-[60%] text-right text-sm font-medium text-neutral-900">
                {value}
            </span>
        </div>
    );
}

export default function SettingsIndex({ settings }: Props) {
    return (
        <AppShell title="Settings">
            <Head title="Settings" />

            <p className="mb-4 max-w-3xl text-sm text-neutral-600">
                The effective platform configuration. Settings change by
                environment (config files and env variables) — this page makes
                them visible to platform staff; there is no write path here.
            </p>

            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>AI layer</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <Row label="Provider" value={settings.ai.provider} />
                        <Row
                            label="Default model"
                            value={settings.ai.default_model}
                        />
                        <Row
                            label="Embedding model"
                            value={settings.ai.embedding_model}
                        />
                        <Row
                            label="API key"
                            value={
                                settings.ai.key_configured
                                    ? 'configured'
                                    : 'not set (fake provider)'
                            }
                        />
                        <Row
                            label="Available providers"
                            value={settings.ai.available_providers.join(', ')}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>OCR</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <Row label="Driver" value={settings.ocr.driver} />
                        <Row label="Languages" value={settings.ocr.language} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>SIHANDAL</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <Row
                            label="Base URL"
                            value={settings.sihandal.base_url}
                        />
                        <Row
                            label="Request timeout"
                            value={`${settings.sihandal.timeout}s`}
                        />
                        <Row
                            label="Cache TTL"
                            value={`${settings.sihandal.cache_ttl}s`}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Validation engine</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <Row
                            label="Low confidence threshold"
                            value={String(settings.validation.low_confidence)}
                        />
                        <Row
                            label="Review confidence threshold"
                            value={String(
                                settings.validation.review_confidence,
                            )}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Documents</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <Row
                            label="Storage disk"
                            value={settings.documents.disk}
                        />
                        <Row
                            label="Max upload size"
                            value={`${settings.documents.max_size_kb} KB`}
                        />
                        <Row
                            label="Allowed extensions"
                            value={settings.documents.allowed_extensions.join(
                                ', ',
                            )}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Infrastructure</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <Row label="Queue connection" value={settings.queue} />
                    </CardContent>
                </Card>
            </div>
        </AppShell>
    );
}
