export type Role = {
    id: number;
    slug: string;
    name: string;
};

export type Opd = {
    id: number;
    code: string;
    name: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    role?: Role | null;
    opd?: Opd | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
};

export type Auth = {
    user: User;
};
