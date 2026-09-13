import { cn } from '@/lib/utils';

/**
 * The application's logo: the mark followed by the name.
 *
 * The name is live text rather than part of the image. The supplied lockup
 * writes it in a dark navy that all but disappears against the dark theme, and
 * text also stays sharp at any size and is what a screen reader announces —
 * which is why the mark beside it is marked decorative.
 */
export default function Brand({ className }: { className?: string }) {
    return (
        <span className={cn('flex items-center gap-2', className)}>
            <img
                src="/taskly-mark.png"
                alt=""
                width={128}
                height={128}
                className="size-7"
            />

            <span className="text-[18px] font-medium tracking-tight">
                Taskly
            </span>
        </span>
    );
}
