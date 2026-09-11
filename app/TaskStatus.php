<?php

namespace App;

enum TaskStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * The label shown to the user.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Não iniciada',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluída',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * The options a status dropdown offers, in the order they are listed.
     *
     * Built from the enum itself, so a case cannot be added without showing up
     * in the interface.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases()
        );
    }
}
