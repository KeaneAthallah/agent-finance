import { useCallback, useEffect, useState } from 'react';

export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'agent-finance.theme';

function isPreference(
    value: string | null | undefined,
): value is ThemePreference {
    return value === 'light' || value === 'dark' || value === 'system';
}

function storage(): Storage | null {
    try {
        return typeof window !== 'undefined' && window.localStorage
            ? window.localStorage
            : null;
    } catch {
        return null;
    }
}

export function getThemePreference(): ThemePreference {
    const stored = storage()?.getItem(STORAGE_KEY);

    return isPreference(stored) ? stored : 'system';
}

export function systemPrefersDark(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-color-scheme: dark)').matches
    );
}

export function applyTheme(preference: ThemePreference): void {
    const dark =
        preference === 'dark' ||
        (preference === 'system' && systemPrefersDark());

    document.documentElement.classList.toggle('dark', dark);
}

export function setThemePreference(preference: ThemePreference): void {
    try {
        storage()?.setItem(STORAGE_KEY, preference);
    } catch {
        /* storage unavailable — theme still applies for this session */
    }

    applyTheme(preference);
}

/**
 * Reactive theme control. Keeps the document root in sync with the
 * stored preference and follows OS changes while in "system" mode.
 *
 * SSR-safe: the initial state matches the server render ('system'),
 * and the stored preference is adopted in an effect after mount, so
 * hydration never sees a localStorage-driven mismatch. The pre-paint
 * script in app.blade.php has already applied the stored theme, so
 * the effect only syncs the toggle UI — no flash.
 */
export function useTheme(): {
    preference: ThemePreference;
    resolved: 'light' | 'dark';
    setPreference: (preference: ThemePreference) => void;
} {
    const [preference, setPreferenceState] =
        useState<ThemePreference>('system');
    const [resolved, setResolved] = useState<'light' | 'dark'>(() =>
        systemPrefersDark() ? 'dark' : 'light',
    );

    // Adopt the stored preference once, after the initial render.
    useEffect(() => {
        setPreferenceState(getThemePreference());
    }, []);

    useEffect(() => {
        applyTheme(preference);
        setResolved(
            preference === 'dark' ||
                (preference === 'system' && systemPrefersDark())
                ? 'dark'
                : 'light',
        );

        if (
            preference !== 'system' ||
            typeof window.matchMedia !== 'function'
        ) {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => {
            applyTheme('system');
            setResolved(media.matches ? 'dark' : 'light');
        };

        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, [preference]);

    const setPreference = useCallback((next: ThemePreference) => {
        setPreferenceState(next);
        setThemePreference(next);
    }, []);

    return { preference, resolved, setPreference };
}
