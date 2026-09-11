<?php

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
            $table->integer('position')->default(0)->after('project_id');

            // The list is read as "the tasks of one project, in order", which
            // this index serves directly.
            $table->index(['project_id', 'position']);
        });

        $this->seedPositionsFromCreationOrder();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'position']);
            $table->dropColumn('position');
        });
    }

    /**
     * Give the rows that already exist the order they are shown in today.
     *
     * Without this every task would sit at position 0 and the list would fall
     * back to the id tiebreaker, quietly reshuffling lists people already know.
     * Done in PHP rather than one window-function UPDATE so the migration does
     * not depend on the database engine.
     */
    private function seedPositionsFromCreationOrder(): void
    {
        DB::table('tasks')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'project_id'])
            ->groupBy('project_id')
            ->each(function ($tasks): void {
                $tasks->values()->each(function ($task, int $index): void {
                    DB::table('tasks')
                        ->where('id', $task->id)
                        ->update(['position' => $index]);
                });
            });
    }
};
