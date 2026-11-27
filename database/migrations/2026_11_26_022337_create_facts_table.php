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
        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->text('statement');
            $table->string('kind')->default('fact');
            $table->decimal('confidence', 3, 2)->default(1);
            $table->string('provenance');
            $table->foreignId('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->index()->constrained('facts')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable();
            $table->boolean('notable_change')->default(false);
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamp('last_retrieved_at')->nullable();
            $table->vector('embedding', 1024)->nullable();
            $table->tsvector('search')->storedAs("to_tsvector('english', statement)");
            $table->timestamps();

            $table->index('search', algorithm: 'gin');
            $table->vectorIndex('embedding');
        });

        Schema::create('fact_subjects', function (Blueprint $table) {
            $table->foreignId('fact_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->primary(['fact_id', 'subject_type', 'subject_id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fact_subjects');
        Schema::dropIfExists('facts');
    }
};
