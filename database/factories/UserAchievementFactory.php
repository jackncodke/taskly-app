<?php

namespace Database\Factories;

use App\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAchievement>
 */
class UserAchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'achievement' => fake()->randomElement(Achievement::cases()),
            'unlocked_at' => now(),
        ];
    }

    /**
     * A badge already earned, at the given achievement.
     */
    public function unlocked(Achievement $achievement): static
    {
        return $this->state(fn (): array => ['achievement' => $achievement]);
    }
}
