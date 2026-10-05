import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { useTheme } from '@/lib/theme';
import {
    LockIcon,
    MailIcon,
    MonitorIcon,
    MoonIcon,
    SunIcon,
} from '@/components/icons';
import { Button, Input, Label } from '@/components/ui';

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
            <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-10">
                <div className="mb-8 flex items-center gap-2.5">
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-sm font-bold text-brand-foreground">
                        AF
                    </span>
                    <div>
                        <p className="text-lg font-semibold tracking-tight text-foreground">
                            Agent Finance
                        </p>
                        <p className="text-xs text-subtle">
                            Financial document intelligence for SIHANDAL
                        </p>
                    </div>
                </div>

                <div className="w-full max-w-sm">
                    <div className="rounded-xl border border-border bg-surface p-6 shadow-sm">
                        <h1 className="mb-1 text-base font-semibold text-foreground">
                            Sign in
                        </h1>
                        <p className="mb-5 text-sm text-muted">
                            Enter your credentials to access the workspace.
                        </p>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <div className="relative">
                                    <MailIcon
                                        size={15}
                                        className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-subtle"
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
                                        className="pl-8"
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
                                        className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-subtle"
                                    />
                                    <Input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
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
                                        className="pr-16 pl-8"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword((value) => !value)
                                        }
                                        className="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer rounded px-1.5 py-1 text-xs font-medium text-muted hover:bg-elevated hover:text-foreground"
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
                                {form.processing ? 'Signing in…' : 'Sign in'}
                            </Button>
                        </form>
                    </div>

                    {/* Theme preference */}
                    <div className="mt-4 flex items-center justify-center gap-1">
                        <span className="mr-1 text-xs text-subtle">Theme</span>
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
                                className={`flex h-8 w-8 cursor-pointer items-center justify-center rounded-md border transition-colors ${
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
        </>
    );
}
