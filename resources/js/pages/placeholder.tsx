import { Head } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import { Badge, Card, CardContent } from '@/components/ui';
import type { InertiaPageProps } from '@/types';

type Props = InertiaPageProps & {
    title: string;
    description: string;
    phase: string;
};

export default function Placeholder({ title, description, phase }: Props) {
    return (
        <AppShell title={title}>
            <Head title={title} />

            <Card>
                <CardContent className="p-6">
                    <Badge variant="blue">{phase}</Badge>
                    <h2 className="mt-3 text-base font-semibold text-neutral-900">
                        {title}
                    </h2>
                    <p className="mt-1 text-sm text-neutral-600">
                        {description}
                    </p>
                    <p className="mt-4 text-xs text-neutral-400">
                        This section is scaffolded with real navigation and
                        authorization. Its features land in the phase shown
                        above — see docs/IMPLEMENTATION_PLAN.md.
                    </p>
                </CardContent>
            </Card>
        </AppShell>
    );
}
