<?php

namespace App;

enum Achievement: string
{
    case FirstTask = 'first_task';
    case TenTasks = 'ten_tasks';
    case FiftyTasks = 'fifty_tasks';
    case HundredTasks = 'hundred_tasks';
    case WeekStreak = 'week_streak';
    case MonthStreak = 'month_streak';
    case AlwaysOnTime = 'always_on_time';
    case CleanSweep = 'clean_sweep';

    /**
     * The name shown on the badge.
     */
    public function label(): string
    {
        return match ($this) {
            self::FirstTask => 'Primeiro passo',
            self::TenTasks => 'Ritmo constante',
            self::FiftyTasks => 'Meio caminho',
            self::HundredTasks => 'Centurião',
            self::WeekStreak => 'Semana de foco',
            self::MonthStreak => 'Mês de foco',
            self::AlwaysOnTime => 'Sempre no prazo',
            self::CleanSweep => 'Faxina geral',
        };
    }

    /**
     * What it takes to earn the badge.
     *
     * Shown on the locked badge as well as the unlocked one: a badge nobody can
     * read the condition of is a badge nobody can aim at.
     */
    public function description(): string
    {
        return match ($this) {
            self::FirstTask => 'Conclua sua primeira tarefa',
            self::TenTasks => 'Conclua 10 tarefas',
            self::FiftyTasks => 'Conclua 50 tarefas',
            self::HundredTasks => 'Conclua 100 tarefas',
            self::WeekStreak => 'Conclua algo em 7 dias seguidos',
            self::MonthStreak => 'Conclua algo em 30 dias seguidos',
            self::AlwaysOnTime => 'Conclua 10 tarefas antes do prazo',
            self::CleanSweep => 'Zere um projeto de 3 tarefas ou mais',
        };
    }

    /**
     * Whether this badge is earned by the progress described.
     *
     * Every case is answered from the one snapshot the caller already has, so
     * checking the whole catalogue costs no queries at all.
     *
     * The streaks are measured against the longest one rather than the current
     * one. At the moment a badge is won the two are the same, and going by the
     * record means a badge that was somehow missed is still handed out on the
     * next completion, instead of becoming unreachable the day the streak ends.
     *
     * @param  array{completed: int, on_time: int, longest_streak: int, clean_projects: int}  $progress
     */
    public function isEarnedBy(array $progress): bool
    {
        return match ($this) {
            self::FirstTask => $progress['completed'] >= 1,
            self::TenTasks => $progress['completed'] >= 10,
            self::FiftyTasks => $progress['completed'] >= 50,
            self::HundredTasks => $progress['completed'] >= 100,
            self::WeekStreak => $progress['longest_streak'] >= 7,
            self::MonthStreak => $progress['longest_streak'] >= 30,
            self::AlwaysOnTime => $progress['on_time'] >= 10,
            self::CleanSweep => $progress['clean_projects'] >= 1,
        };
    }
}
