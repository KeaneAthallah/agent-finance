import { Head } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import { Badge, Card, CardContent } from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type Counters = {
    uploaded: number;
    processing: number;
    needs_review: number;
    approved: number;
    synced: number;
    failed: number;
};

type Props = InertiaPageProps & {
    counters: Counters;
};

const STATUS_LABELS: {
    key: keyof Counters;
    label: string;
    variant: 'blue' | 'amber' | 'green' | 'purple' | 'red' | 'neutral';
}[] = [
    { key: 'uploaded', label: 'Uploaded', variant: 'neutral' },
    { key: 'processing', label: 'In pipeline', variant: 'blue' },
    { key: 'needs_review', label: 'Needs review', variant: 'amber' },
    { key: 'approved', label: 'Approved', variant: 'green' },
    { key: 'synced', label: 'Synced to SIHANDAL', variant: 'purple' },
    { key: 'failed', label: 'Failed', variant: 'red' },
];

export default function Dashboard({ counters }: Props) {
    return (
        <AppShell title="Dashboard">
            <Head title="Dashboard" />

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {STATUS_LABELS.map(({ key, label, variant }) => (
                    <Card key={key}>
                        <CardContent className="p-4">
                            <p className="text-2xl font-semibold text-neutral-900 tabular-nums">
                                {counters[key]}
                            </p>
                            <Badge variant={variant} className="mt-2">
                                {label}
                            </Badge>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent className="p-5">
                        <h2 className="text-sm font-semibold text-neutral-900">
                            Pipeline health
                        </h2>
                        <p className="mt-1 text-sm text-neutral-500">
                            Stage-by-stage processing status arrives with the
                            queue infrastructure in Phase 3. Counters above will
                            reflect live job state — no fake progress.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5">
                        <h2 className="text-sm font-semibold text-neutral-900">
                            Getting started
                        </h2>
                        <ul className="mt-2 space-y-1 text-sm text-neutral-600">
                            <li>• Document upload lands in Phase 2.</li>
                            <li>
                                • OCR + classification pipelines land in Phase
                                3.
                            </li>
                            <li>
                                • AI extraction with evidence lands in Phase 4.
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppShell>
    );
}
