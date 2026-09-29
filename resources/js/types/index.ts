export type * from './auth';

/**
 * Props shared with every page by HandleInertiaRequests.
 */
export type InertiaPageProps = {
    name: string;
    auth: import('./auth').Auth;
    [key: string]: unknown;
};

export type SharedProps = InertiaPageProps;
