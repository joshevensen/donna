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
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('summary')->nullable();
            $table->timestamp('first_discussed_at')->nullable();
            $table->timestamp('last_discussed_at')->nullable();
            $table->vector('embedding', 1024)->nullable();
            $table->timestamps();

            $table->vectorIndex('embedding');
        });

        Schema::create('message_topic', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();

            $table->primary(['message_id', 'topic_id']);
            $table->index('topic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_topic');
        Schema::dropIfExists('topics');
    }
};
