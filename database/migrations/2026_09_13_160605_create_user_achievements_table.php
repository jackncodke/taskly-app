<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // A plain string for the same reason `tasks.status` is one: adding
            // an achievement should be a change to the enum, not to the schema.
            $table->string('achievement');
            $table->timestamp('unlocked_at');
            $table->timestamps();

            // An achievement is unlocked once and stays unlocked. The guarantee
            // lives here rather than only in PHP, so a second writer racing the
            // first cannot hand out the same badge twice.
            $table->unique(['user_id', 'achievement']);
        });

        $this->awardTheBadgesAlreadyEarned();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }

    /**
     * Hand out the badges the work already in the database has earned.
     *
     * Badges are awarded as a task is completed, which means that without this
     * a user arriving with a hundred finished tasks behind them would open the
     * panel to an empty case and stay there until they finished one more. The
     * date on these is today, because today is when the badge was granted; the
     * panel will mark them as freshly won for the first day, which is a fair
     * description of what just happened.
     */
    private function awardTheBadgesAlreadyEarned(): void
    {
        User::query()->each(function (User $user): void {
            $user->unlockEarnedAchievements();
        });
    }
};
