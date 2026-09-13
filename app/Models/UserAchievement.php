<?php

namespace App\Models;

use App\Achievement;
use Database\Factories\UserAchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A badge a user has earned, and the moment they earned it.
 *
 * Points, levels and streaks are all recomputed from the tasks themselves, so
 * they can never drift from the data. A badge is the exception: it records
 * something that happened, so it is written down and kept — the user keeps the
 * badge even if they later delete the very project that earned it.
 *
 * @property int $id
 * @property int $user_id
 * @property Achievement $achievement
 * @property Carbon $unlocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['achievement', 'unlocked_at'])]
class UserAchievement extends Model
{
    /** @use HasFactory<UserAchievementFactory> */
    use HasFactory;

    /**
     * The user who earned the badge.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'achievement' => Achievement::class,
            'unlocked_at' => 'datetime',
        ];
    }
}
