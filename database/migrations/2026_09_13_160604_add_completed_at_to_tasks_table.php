<?php

use App\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // `status` says where a task stands now; it never said when it got
            // there. Points, streaks and achievements are all questions about
            // when, so the moment has to be a column of its own.
            $table->timestamp('completed_at')->nullable()->after('due_at');

            // Progress is read as "the finished tasks of these projects", which
            // this index serves directly.
            $table->index(['project_id', 'completed_at']);
        });

        $this->seedCompletionsFromLastWrite();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'completed_at']);
            $table->dropColumn('completed_at');
        });
    }

    /**
     * Date the tasks that were already finished before the column existed.
     *
     * `updated_at` is the closest thing on the row to the moment it was closed:
     * for a task nobody touched afterwards it is exactly right, and for the rest
     * it is late rather than absent. Leaving them null instead would tell every
     * existing user their finished work never happened.
     */
    private function seedCompletionsFromLastWrite(): void
    {
        DB::table('tasks')
            ->where('status', TaskStatus::Completed->value)
            ->update(['completed_at' => DB::raw('updated_at')]);
    }
};
