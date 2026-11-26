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
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->string('batch_id')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamp('results_applied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
    }
};
