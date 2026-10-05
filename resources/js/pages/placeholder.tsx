import { Head } from '@inertiajs/react';
import { AppShell } from '@/components/layout/app-shell';
import { ConstructionIcon } from '@/components/icons';
import { Badge, Card, CardContent, EmptyState } from '@/components/ui';
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
                    <h2 className="mt-3 text-base font-semibold text-foreground">
                        {title}
                    </h2>
                    <p className="mt-1 text-sm text-muted">{description}</p>
                    <p className="mt-4 text-xs text-subtle">
                        This section is scaffolded with real navigation and
                        authorization. Its features land in the phase shown
                        above — see docs/IMPLEMENTATION_PLAN.md.
                    </p>
                </CardContent>
            </Card>

            <div className="mt-4">
                <EmptyState
                    icon={<ConstructionIcon size={20} />}
                    title="Coming soon"
                    description="This area is still being built. The navigation and authorization around it are already live."
                />
            </div>
        </AppShell>
    );
}
