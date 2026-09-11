import { useEffect, useRef, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ModalProps = {
    open: boolean;
    onClose: () => void;
    title: string;
    /** `lg` fits a multi-field form; `sm` suits a short confirmation. */
    size?: 'sm' | 'lg';
    children: ReactNode;
};

export default function Modal({
    open,
    onClose,
    title,
    size = 'sm',
    children,
}: ModalProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = dialogRef.current;

        if (!dialog) {
            return;
        }

        if (open && !dialog.open) {
            dialog.showModal();
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby="modal-title"
            onClose={onClose}
            onClick={(event) => {
                // A click landing on the dialog itself is a backdrop click:
                // the content sits inside the wrapper below.
                if (event.target === dialogRef.current) {
                    onClose();
                }
            }}
            className={cn(
                'm-auto w-[calc(100%-2rem)] rounded-lg bg-white p-0 text-[#1b1b18] shadow-lg',
                // A tall form stays scrollable instead of running off the
                // viewport on a short screen.
                'max-h-[calc(100dvh-4rem)] backdrop:bg-black/40',
                'dark:bg-[#161615] dark:text-[#EDEDEC]',
                size === 'lg' ? 'max-w-[560px]' : 'max-w-[420px]',
            )}
        >
            <div className="flex flex-col gap-5 p-6">
                <h2 id="modal-title" className="text-[16px] font-medium">
                    {title}
                </h2>

                {children}
            </div>
        </dialog>
    );
}
