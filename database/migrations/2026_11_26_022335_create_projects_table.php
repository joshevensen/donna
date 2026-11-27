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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->jsonb('aliases')->default('[]');
            $table->foreignId('owner_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('tier')->nullable();
            $table->string('status')->default('proposed');
            $table->text('summary')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_topic', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();

            $table->primary(['project_id', 'topic_id']);
            $table->index('topic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_topic');
        Schema::dropIfExists('projects');
    }
};
