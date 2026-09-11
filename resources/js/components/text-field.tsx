import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

type TextFieldProps = ComponentProps<'input'> & {
    label: string;
    name: string;
    error?: string;
};

export default function TextField({
    label,
    name,
    error,
    className,
    ...props
}: TextFieldProps) {
    return (
        <div className="flex flex-col gap-1.5">
            <label
                htmlFor={name}
                className="text-[13px] font-medium text-[#1b1b18] dark:text-[#EDEDEC]"
            >
                {label}
            </label>

            <input
                {...props}
                id={name}
                name={name}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${name}-error` : undefined}
                className={cn(
                    'w-full rounded-md border border-[#e3e3e0] bg-white px-3 py-2 text-[14px] text-[#1b1b18] shadow-xs outline-none',
                    'placeholder:text-[#a1a09a] focus:border-[#1b1b18] focus:ring-2 focus:ring-[#1b1b18]/10',
                    'dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-[#EDEDEC] dark:focus:border-[#EDEDEC] dark:focus:ring-[#EDEDEC]/15',
                    error &&
                        'border-red-500 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500',
                    className,
                )}
            />

            <span id={`${name}-error`}>
                <InputError message={error} />
            </span>
        </div>
    );
}
