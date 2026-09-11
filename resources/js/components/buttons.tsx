import { cn } from '@/lib/utils';

export const primaryButtonClasses =
    'rounded-md bg-[#1b1b18] px-4 py-2 text-[14px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-60 dark:bg-[#EDEDEC] dark:text-[#1b1b18]';

export const secondaryButtonClasses =
    'rounded-md border border-[#e3e3e0] px-4 py-2 text-[14px] font-medium transition-colors hover:bg-[#f4f4f2] dark:border-[#3E3E3A] dark:hover:bg-[#0a0a0a]';

export const dangerButtonClasses =
    'rounded-md bg-red-600 px-4 py-2 text-[14px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-60';

/**
 * The icon-only buttons in the application header, which share the border and
 * hover treatment of the "Sair" button next to them. The caller sets the box
 * size, and `headerButtonSize` is the baseline the others are measured against.
 */
export const headerButtonClasses =
    'flex items-center justify-center rounded-md border border-[#e3e3e0] transition-colors hover:bg-[#f4f4f2] dark:border-[#3E3E3A] dark:hover:bg-[#161615]';

/** 28px, which is the height "Sair" gets from its text and padding. */
export const headerButtonSize = 'size-7';

export const iconButtonClasses =
    'rounded p-1 text-[#706f6c] transition-colors hover:bg-[#e9e9e6] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:bg-[#2a2a28] dark:hover:text-[#EDEDEC]';

/**
 * The "+ <something>" button that opens a create form.
 *
 * Shared between the project sidebar and the task list so the two stay
 * identical by construction rather than by two copies of the same classes
 * drifting apart.
 */
export function AddButton({
    label,
    onClick,
    className,
}: {
    label: string;
    onClick: () => void;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-center justify-center gap-2 rounded-md bg-[#1b1b18] px-4 py-2 text-[14px] font-medium text-white transition-opacity hover:opacity-90 dark:bg-[#EDEDEC] dark:text-[#1b1b18]',
                className,
            )}
        >
            <span aria-hidden="true" className="text-[16px] leading-none">
                +
            </span>
            {label}
        </button>
    );
}
