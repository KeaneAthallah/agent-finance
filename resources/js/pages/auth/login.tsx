import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Input, Label } from '@/components/ui';
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(AuthController.store().url);
    }

    return (
        <>
            <Head title="Sign in" />
            <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
                <div className="w-full max-w-sm">
                    <div className="mb-6 flex items-center justify-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded bg-blue-700 text-sm font-bold text-white">
                            AF
                        </div>
                        <span className="text-lg font-semibold text-neutral-900">
                            Agent Finance
                        </span>
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                        <h1 className="mb-1 text-base font-semibold text-neutral-900">
                            Sign in
                        </h1>
                        <p className="mb-4 text-sm text-neutral-500">
                            AI-powered financial document intelligence for
                            SIHANDAL.
                        </p>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {form.errors.email ? (
                                    <p className="mt-1 text-xs text-red-600">
                                        {form.errors.email}
                                    </p>
                                ) : null}
                            </div>

                            <div>
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                    required
                                />
                                {form.errors.password ? (
                                    <p className="mt-1 text-xs text-red-600">
                                        {form.errors.password}
                                    </p>
                                ) : null}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-neutral-600">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border-neutral-300"
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
                </div>
            </div>
        </>
    );
}
