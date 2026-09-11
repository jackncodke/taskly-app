export default function InputError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p role="alert" className="text-[13px] text-red-600 dark:text-red-400">
            {message}
        </p>
    );
}
