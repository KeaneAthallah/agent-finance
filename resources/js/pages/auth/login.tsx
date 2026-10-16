import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { useTheme } from '@/lib/theme';
import {
    AiIcon,
    DocumentsIcon,
    LockIcon,
    MailIcon,
    MonitorIcon,
    MoonIcon,
    SihandalIcon,
    SunIcon,
    ValidationIcon,
} from '@/components/icons';
import { Button, Input, Label } from '@/components/ui';

const HIGHLIGHTS = [
    {
        icon: DocumentsIcon,
        title: 'OCR + AI extraction',
        description: 'Structured fields pulled from every SPM, SP2D and SPP.',
    },
    {
        icon: ValidationIcon,
        title: 'Automated validation',
        description: 'Totals, fiscal year and completeness checked instantly.',
    },
    {
        icon: SihandalIcon,
        title: 'SIHANDAL sync',
        description: 'Confirmed documents written to the system of record.',
    },
    {
        icon: AiIcon,
        title: 'Grounded assistant',
        description: 'Answers labeled by trust level — facts, not guesses.',
    },
];

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const { preference, setPreference } = useTheme();
    const [showPassword, setShowPassword] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(AuthController.store().url);
    }

    return (
        <>
            <Head title="Sign in" />
            <div className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
                {/* Brand panel */}
                <div className="relative hidden overflow-hidden bg-gradient-to-br from-brand via-brand-strong to-purple p-10 text-white lg:flex lg:flex-col lg:justify-between">
                    <div
                        aria-hidden
                        className="bg-grid pointer-events-none absolute inset-0 opacity-[0.12]"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-white/10 blur-3xl"
                    />

                    <div className="relative flex items-center gap-3">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-sm font-bold backdrop-blur">
                            AF
                        </span>
                        <div>
                            <p className="text-base font-semibold tracking-tight">
                                Agent Finance
                            </p>
                            <p className="text-xs text-white/70">
                                Financial document intelligence
                            </p>
                        </div>
                    </div>

                    <div className="relative max-w-md">
                        <h2 className="text-3xl font-semibold tracking-tight">
                            Audit local-government finances with confidence.
                        </h2>
                        <p className="mt-3 text-sm text-white/75">
                            From upload to SIHANDAL sync — extraction,
                            validation and human review in one trustworthy
                            workspace.
                        </p>

                        <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                            {HIGHLIGHTS.map((item) => (
                                <li
                                    key={item.title}
                                    className="rounded-xl border border-white/15 bg-white/5 p-3.5 backdrop-blur"
                                >
                                    <item.icon
                                        size={18}
                                        className="text-white/90"
                                    />
                                    <p className="mt-2 text-sm font-medium">
                                        {item.title}
                                    </p>
                                    <p className="mt-0.5 text-xs text-white/70">
                                        {item.description}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative text-xs text-white/60">
                        Read-only assistant · OPD-scoped access · Full audit
                        trail
                    </p>
                </div>

                {/* Form panel */}
                <div className="flex flex-col items-center justify-center bg-background px-4 py-10">
                    <div className="mb-8 flex items-center gap-3 lg:hidden">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-purple text-sm font-bold text-brand-foreground shadow-sm">
                            AF
                        </span>
                        <div>
                            <p className="text-base font-semibold tracking-tight text-foreground">
                                Agent Finance
                            </p>
                            <p className="text-xs text-subtle">
                                Financial document intelligence for SIHANDAL
                            </p>
                        </div>
                    </div>

                    <div className="w-full max-w-sm">
                        <div className="rounded-2xl border border-border bg-surface p-6 shadow-lg sm:p-8">
                            <h1 className="text-lg font-semibold tracking-tight text-foreground">
                                Sign in
                            </h1>
                            <p className="mt-1 mb-6 text-sm text-muted">
                                Enter your credentials to access the workspace.
                            </p>

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <Label htmlFor="email">Email</Label>
                                    <div className="relative">
                                        <MailIcon
                                            size={15}
                                            className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-subtle"
                                        />
                                        <Input
                                            id="email"
                                            type="email"
                                            autoComplete="email"
                                            value={form.data.email}
                                            onChange={(e) =>
                                                form.setData(
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                            autoFocus
                                            className="pl-9"
                                            placeholder="you@example.com"
                                        />
                                    </div>
                                    {form.errors.email ? (
                                        <p className="mt-1 text-xs text-danger">
                                            {form.errors.email}
                                        </p>
                                    ) : null}
                                </div>

                                <div>
                                    <Label htmlFor="password">Password</Label>
                                    <div className="relative">
                                        <LockIcon
                                            size={15}
                                            className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-subtle"
                                        />
                                        <Input
                                            id="password"
                                            type={
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            autoComplete="current-password"
                                            value={form.data.password}
                                            onChange={(e) =>
                                                form.setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                            className="pr-16 pl-9"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword(
                                                    (value) => !value,
                                                )
                                            }
                                            className="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer rounded-md px-1.5 py-1 text-xs font-medium text-muted hover:bg-elevated hover:text-foreground"
                                            aria-label={
                                                showPassword
                                                    ? 'Hide password'
                                                    : 'Show password'
                                            }
                                        >
                                            {showPassword ? 'Hide' : 'Show'}
                                        </button>
                                    </div>
                                    {form.errors.password ? (
                                        <p className="mt-1 text-xs text-danger">
                                            {form.errors.password}
                                        </p>
                                    ) : null}
                                </div>

                                <label className="flex cursor-pointer items-center gap-2 text-sm text-muted">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-border-strong accent-[var(--brand)]"
                                        checked={form.data.remember}
                                        onChange={(e) =>
                                            form.setData(
                                                'remember',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Remember me
                                </label>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? 'Signing in…'
                                        : 'Sign in'}
                                </Button>
                            </form>
                        </div>

                        {/* Theme preference */}
                        <div className="mt-4 flex items-center justify-center gap-1">
                            <span className="mr-1 text-xs text-subtle">
                                Theme
                            </span>
                            {(
                                [
                                    {
                                        value: 'light',
                                        label: 'Light',
                                        icon: SunIcon,
                                    },
                                    {
                                        value: 'dark',
                                        label: 'Dark',
                                        icon: MoonIcon,
                                    },
                                    {
                                        value: 'system',
                                        label: 'System',
                                        icon: MonitorIcon,
                                    },
                                ] as const
                            ).map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => setPreference(option.value)}
                                    aria-label={option.label}
                                    aria-pressed={preference === option.value}
                                    title={option.label}
                                    className={`flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border transition-colors ${
                                        preference === option.value
                                            ? 'border-brand/50 bg-brand-subtle text-brand'
                                            : 'border-border bg-surface text-muted hover:bg-elevated hover:text-foreground'
                                    }`}
                                >
                                    <option.icon size={15} />
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
