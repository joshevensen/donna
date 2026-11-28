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
        Schema::create('file_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('heading_path')->nullable();
            $table->text('body');
            $table->char('content_hash', 64);
            $table->vector('embedding', 1024)->nullable();
            $table->tsvector('search')->storedAs("to_tsvector('english', coalesce(heading_path, '') || ' ' || body)");
            $table->timestamps();

            $table->index(['file_id', 'position']);
            $table->index('search', algorithm: 'gin');
            $table->vectorIndex('embedding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_chunks');
    }
};
