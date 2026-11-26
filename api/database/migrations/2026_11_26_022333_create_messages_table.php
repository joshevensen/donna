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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('author');
            $table->text('body');
            $table->string('source')->default('terminal');
            $table->string('status')->default('complete');
            $table->foreignId('in_reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->text('error')->nullable();
            $table->jsonb('meta')->nullable();
            $table->vector('embedding', 1024)->nullable();
            $table->tsvector('search')->storedAs("to_tsvector('english', body)");
            $table->timestamps();

            $table->index('created_at');
            $table->index('search', algorithm: 'gin');
            $table->vectorIndex('embedding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
