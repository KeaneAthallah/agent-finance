import type { SVGProps } from 'react';

/**
 * Inline stroke icon set (24×24, currentColor, no runtime deps).
 * Every icon is decorative-only when paired with a visible label —
 * keep `aria-hidden` on icon-only usages and label toggle buttons.
 */
type IconProps = SVGProps<SVGSVGElement> & { size?: number };

function base({ size = 18, ...props }: IconProps) {
    return {
        width: size,
        height: size,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.8,
        strokeLinecap: 'round' as const,
        strokeLinejoin: 'round' as const,
        'aria-hidden': true,
        ...props,
    };
}

export function DashboardIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
        </svg>
    );
}

export function DocumentsIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
            <path d="M14 3v5h5" />
            <path d="M9 13h6M9 17h4" />
        </svg>
    );
}

export function AiIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 3l1.7 4.6L18 9.2l-4.3 1.6L12 15.5l-1.7-4.7L6 9.2l4.3-1.6Z" />
            <path d="M18.5 14.5l.9 2.3 2.1.8-2.1.8-.9 2.3-.9-2.3-2.1-.8 2.1-.8Z" />
            <path d="M6 15.5l.7 1.7 1.6.6-1.6.6-.7 1.7-.7-1.7-1.6-.6 1.6-.6Z" />
        </svg>
    );
}

export function ReviewIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M9 6h11M9 12h11M9 18h11" />
            <path d="m4 5 1.2 1.2L7.5 4" />
            <path d="m4 11 1.2 1.2L7.5 10" />
            <path d="m4 17 1.2 1.2L7.5 16" />
        </svg>
    );
}

export function ValidationIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 3 5 6v5c0 4.4 3 8.4 7 10 4-1.6 7-5.6 7-10V6Z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}

export function AnomaliesIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 4 2.5 20h19Z" />
            <path d="M12 10v4" />
            <circle cx="12" cy="17" r="0.4" fill="currentColor" />
        </svg>
    );
}

export function ReportsIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 20V10M10 20V4M16 20v-7M21 20H3" />
        </svg>
    );
}

export function SihandalIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M10 14a5 5 0 0 0 7.1 0l2.4-2.4a5 5 0 0 0-7-7.1l-1.4 1.4" />
            <path d="M14 10a5 5 0 0 0-7.1 0l-2.4 2.4a5 5 0 0 0 7 7.1l1.4-1.4" />
        </svg>
    );
}

export function ActivityIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M3 12h4l2.5-7 4 14 2.5-7H21" />
        </svg>
    );
}

export function SettingsIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="3" />
            <path d="M12 2.5v2.2M12 19.3v2.2M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6" />
        </svg>
    );
}

export function UsersIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="9" cy="8" r="3.5" />
            <path d="M2.5 20c.8-3.2 3.4-5 6.5-5s5.7 1.8 6.5 5" />
            <circle cx="17" cy="9" r="2.5" />
            <path d="M16 15.2c2.8.3 4.9 1.9 5.5 4.8" />
        </svg>
    );
}

export function BuildingIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="4" y="3" width="16" height="18" rx="1.5" />
            <path d="M9 7.5h2M13 7.5h2M9 11.5h2M13 11.5h2M9 15.5h2M13 15.5h2" />
        </svg>
    );
}

export function SearchIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.8-3.8" />
        </svg>
    );
}

export function BellIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M6 9.5a6 6 0 0 1 12 0c0 5 1.8 6.3 1.8 6.3H4.2S6 14.5 6 9.5" />
            <path d="M10 19.5a2 2 0 0 0 4 0" />
        </svg>
    );
}

export function SunIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2.5V5M12 19v2.5M2.5 12H5M19 12h2.5M5 5l1.8 1.8M17.2 17.2 19 19M19 5l-1.8 1.8M6.8 17.2 5 19" />
        </svg>
    );
}

export function MoonIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a7 7 0 1 0 10.5 10.5Z" />
        </svg>
    );
}

export function MonitorIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="3" y="4" width="18" height="12" rx="1.5" />
            <path d="M9 20h6M12 16v4" />
        </svg>
    );
}

export function MenuIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 6.5h16M4 12h16M4 17.5h16" />
        </svg>
    );
}

export function XIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m5 5 14 14M19 5 5 19" />
        </svg>
    );
}

export function ChevronDownIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m5 9 7 7 7-7" />
        </svg>
    );
}

export function ChevronLeftIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m15 5-7 7 7 7" />
        </svg>
    );
}

export function ChevronRightIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m9 5 7 7-7 7" />
        </svg>
    );
}

export function UploadIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 16V4m0 0-4.5 4.5M12 4l4.5 4.5" />
            <path d="M4 16.5V19a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-2.5" />
        </svg>
    );
}

export function FileIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
            <path d="M14 3v5h5" />
        </svg>
    );
}

export function EyeIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
    );
}

export function DownloadIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 4v12m0 0 4.5-4.5M12 16l-4.5-4.5" />
            <path d="M4 16.5V19a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-2.5" />
        </svg>
    );
}

export function RefreshIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M20 11a8 8 0 0 0-14.9-3M4 13a8 8 0 0 0 14.9 3" />
            <path d="M4 4v4h4M20 20v-4h-4" />
        </svg>
    );
}

export function CheckIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m4.5 12.5 5 5 10-11" />
        </svg>
    );
}

export function CheckCircleIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12.3 2.7 2.7L16.3 9" />
        </svg>
    );
}

export function AlertCircleIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v4.5" />
            <circle cx="12" cy="16.4" r="0.4" fill="currentColor" />
        </svg>
    );
}

export function InfoIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v5" />
            <circle cx="12" cy="7.6" r="0.4" fill="currentColor" />
        </svg>
    );
}

export function LogoutIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M14 4H6a1.5 1.5 0 0 0-1.5 1.5v13A1.5 1.5 0 0 0 6 20h8" />
            <path d="M10 12h10.5M17 8.5l3.5 3.5-3.5 3.5" />
        </svg>
    );
}

export function ClockIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5.2l3.2 1.8" />
        </svg>
    );
}

export function LayersIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m12 3 9 4.5-9 4.5-9-4.5Z" />
            <path d="m3 12.5 9 4.5 9-4.5" />
            <path d="m3 16.5 9 4.5 9-4.5" />
        </svg>
    );
}

export function ScanIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16" />
            <path d="M4 12h16" />
        </svg>
    );
}

export function CpuIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="6" y="6" width="12" height="12" rx="1.5" />
            <rect x="10" y="10" width="4" height="4" />
            <path d="M9 2.5V6M15 2.5V6M9 18v3.5M15 18v3.5M2.5 9H6M2.5 15H6M18 9h3.5M18 15h3.5" />
        </svg>
    );
}

export function FilterIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 5h16l-6.2 7.2V19l-3.6-2v-4.8Z" />
        </svg>
    );
}

export function TrendUpIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m3 17 6-6 4 4 8-8" />
            <path d="M15 7h6v6" />
        </svg>
    );
}

export function MoreIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="5" cy="12" r="0.6" fill="currentColor" />
            <circle cx="12" cy="12" r="0.6" fill="currentColor" />
            <circle cx="19" cy="12" r="0.6" fill="currentColor" />
        </svg>
    );
}

export function LockIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="5" y="11" width="14" height="9" rx="1.5" />
            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
        </svg>
    );
}

export function InboxIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M3 13.5 5.5 5h13L21 13.5V19a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19Z" />
            <path d="M3 13.5h5l1.5 2.5h5L16 13.5h5" />
        </svg>
    );
}

export function CircleIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="8" />
        </svg>
    );
}

export function MinusIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M5 12h14" />
        </svg>
    );
}

export function ListIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M9 6.5h11M9 12h11M9 17.5h11" />
            <path d="M4 6.5h.01M4 12h.01M4 17.5h.01" />
        </svg>
    );
}

export function LayoutGridIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="4" y="4" width="7" height="7" rx="1.25" />
            <rect x="13" y="4" width="7" height="7" rx="1.25" />
            <rect x="4" y="13" width="7" height="7" rx="1.25" />
            <rect x="13" y="13" width="7" height="7" rx="1.25" />
        </svg>
    );
}

export function SendIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m21 3-9.5 9.5" />
            <path d="m21 3-7 18-2.5-8.5L3 10Z" />
        </svg>
    );
}

export function HumanIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="7.5" r="3.5" />
            <path d="M5 20.5c.9-3.6 3.7-5.5 7-5.5s6.1 1.9 7 5.5" />
        </svg>
    );
}

export function SystemIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="7" y="7" width="10" height="10" rx="2" />
            <path d="M9 2.5V7M15 2.5V7M9 17v4.5M15 17v4.5M2.5 9H7M2.5 15H7M17 9h4.5M17 15h4.5" />
        </svg>
    );
}

export function MailIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
        </svg>
    );
}

export function ConstructionIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M2 20h20" />
            <path d="M5 20 12 5l7 15" />
            <path d="M9.5 13h5" />
            <path d="M12 5 9 2h6Z" />
        </svg>
    );
}

export function HeartPulseIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M19.5 12.6 12 20l-7.5-7.4a5 5 0 1 1 7.5-6.6 5 5 0 1 1 7.5 6.6Z" />
            <path d="M3.5 12h4l1.5-2.5 2.5 5 1.5-2.5h4.5" />
        </svg>
    );
}

export function QueueIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 6.5h16" />
            <path d="M4 11.5h16" />
            <path d="M4 16.5h9" />
            <path d="m17 16 2.5 2.5L22 16" />
        </svg>
    );
}
