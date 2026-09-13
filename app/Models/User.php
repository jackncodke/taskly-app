<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Achievement;
use App\TaskStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * What each finished task is worth, and what finishing it early adds.
     */
    private const XP_PER_TASK = 10;

    private const XP_ON_TIME_BONUS = 5;

    /**
     * The projects owned by the user.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Every task across every project the user owns.
     *
     * A task belongs to a project, not to a person, so counting the work of
     * one person meant reaching through the projects at every call site. This
     * says it once.
     *
     * @return HasManyThrough<Task, Project, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    /**
     * The badges the user has earned.
     *
     * @return HasMany<UserAchievement, $this>
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    /**
     * Everything the progress panel counts, worked out from the tasks.
     *
     * Nothing here is stored. A running total would have to be adjusted on
     * every completion and every reopening, and one missed adjustment is a
     * number that stays wrong forever; recomputing cannot drift from the data
     * it is computed from. It costs three queries, which is what the rest of
     * the dashboard costs per panel.
     *
     * @return array{completed: int, on_time: int, streak: int, longest_streak: int, clean_projects: int, xp: int, level: int, xp_into_level: int, xp_for_next_level: int, level_percent: float}
     */
    public function progress(): array
    {
        $completions = $this->tasks()
            ->whereNotNull('tasks.completed_at')
            ->orderByDesc('tasks.completed_at')
            ->pluck('tasks.completed_at');

        $completed = $completions->count();
        $onTime = $this->onTimeCount();

        $xp = $completed * self::XP_PER_TASK + $onTime * self::XP_ON_TIME_BONUS;
        $level = $this->levelFor($xp);

        $reached = self::xpForLevel($level);
        $next = self::xpForLevel($level + 1);

        return [
            'completed' => $completed,
            'on_time' => $onTime,
            ...$this->streaksFrom($completions),
            'clean_projects' => $this->cleanProjectCount(),
            'xp' => $xp,
            'level' => $level,
            'xp_into_level' => $xp - $reached,
            'xp_for_next_level' => $next - $reached,
            'level_percent' => round(($xp - $reached) / ($next - $reached) * 100, 4),
        ];
    }

    /**
     * Write down every badge the user has earned and not yet been given.
     *
     * Called from the Task model the moment a task is completed, so a badge is
     * dated when it was won rather than when the dashboard was next opened.
     * firstOrCreate rather than create because two requests completing two
     * tasks at once would otherwise race into the unique index.
     *
     * @return array<int, Achievement>
     */
    public function unlockEarnedAchievements(): array
    {
        $progress = $this->progress();
        $held = $this->achievements()->pluck('achievement')->all();

        $earned = array_values(array_filter(
            Achievement::cases(),
            fn (Achievement $achievement): bool => ! in_array($achievement, $held, true)
                && $achievement->isEarnedBy($progress),
        ));

        foreach ($earned as $achievement) {
            $this->achievements()->firstOrCreate(
                ['achievement' => $achievement],
                ['unlocked_at' => now()],
            );
        }

        return $earned;
    }

    /**
     * How many tasks were finished before their own deadline.
     *
     * A task with no deadline is neither early nor late, so it is not counted.
     */
    private function onTimeCount(): int
    {
        return $this->tasks()
            ->whereNotNull('tasks.completed_at')
            ->whereNotNull('tasks.due_at')
            ->whereColumn('tasks.completed_at', '<=', 'tasks.due_at')
            ->count();
    }

    /**
     * How many projects have three tasks or more and nothing left open.
     *
     * The floor is there so a project with a single task in it is not a sweep.
     */
    private function cleanProjectCount(): int
    {
        return $this->projects()
            ->has('tasks', '>=', 3)
            ->whereDoesntHave('tasks', function (Builder $query): void {
                $query->where('status', '!=', TaskStatus::Completed);
            })
            ->count();
    }

    /**
     * The current and the longest run of consecutive days with a completion.
     *
     * The timestamps are reduced to calendar days in PHP rather than by a
     * date() in SQL, which is spelled differently on every engine — and what is
     * being read here is the history of one person, not a table scan.
     *
     * A run that ends yesterday still counts as current: the day is not over,
     * and telling someone their streak is gone at breakfast is how a streak
     * stops being worth keeping.
     *
     * @param  Collection<int, mixed>  $completions  completion timestamps, most recent first
     * @return array{streak: int, longest_streak: int}
     */
    private function streaksFrom(Collection $completions): array
    {
        $days = $completions
            ->map(fn ($completedAt): string => Carbon::parse($completedAt)->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return ['streak' => 0, 'longest_streak' => 0];
        }

        $run = 1;
        $longest = 1;
        $leading = 1;

        for ($index = 1; $index < $days->count(); $index++) {
            $gap = (int) Carbon::parse($days[$index])
                ->diffInDays(Carbon::parse($days[$index - 1]));

            $run = $gap === 1 ? $run + 1 : 1;
            $longest = max($longest, $run);

            // The run is still the one reaching the most recent day only while
            // every step back from it has been a single day.
            if ($run === $index + 1) {
                $leading = $run;
            }
        }

        $today = today();

        $endsRecently = in_array($days[0], [
            $today->toDateString(),
            $today->subDay()->toDateString(),
        ], true);

        return [
            'streak' => $endsRecently ? $leading : 0,
            'longest_streak' => $longest,
        ];
    }

    /**
     * The level the given experience has reached.
     */
    private function levelFor(int $xp): int
    {
        $level = 1;

        while ($xp >= self::xpForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    /**
     * The experience a level starts at.
     *
     * Each level costs 50 XP more than the one before it — level 2 at 50, level
     * 3 at 150, level 5 at 500 — so the early ones arrive quickly enough to be
     * worth chasing and the later ones stay worth something.
     */
    private static function xpForLevel(int $level): int
    {
        return 25 * $level * ($level - 1);
    }

    /**
     * Always store email addresses lowercased and trimmed.
     *
     * Canonicalizing here rather than at the request layer keeps the invariant
     * true for every writer — factories, seeders, commands and tinker included
     * — and lets the unique index on `email` actually reject case variants,
     * which Postgres would otherwise treat as distinct values.
     *
     * Null passes through untouched so the column's NOT NULL constraint stays
     * the authority on a missing email, rather than a TypeError raised here.
     *
     * @return Attribute<never, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null
                ? null
                : Str::lower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
