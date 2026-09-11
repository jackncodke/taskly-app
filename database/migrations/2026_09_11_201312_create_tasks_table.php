<?php

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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();

            // Tags live in a json column rather than their own table: nothing in
            // the application queries or aggregates by tag, so a join table
            // would add two writes per save and buy nothing today.
            $table->json('tags')->default('[]');

            $table->timestamps();

            // The task list is always read as "the tasks of one project,
            // newest first", which this index serves directly.
            $table->index(['project_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
