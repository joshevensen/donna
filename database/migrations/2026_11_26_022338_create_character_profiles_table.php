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
        Schema::create('character_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->text('body');
            $table->string('status')->default('proposed');
            $table->text('change_summary')->nullable();
            $table->foreignId('proposed_in_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        // Donna is exactly one person at a time: the database refuses a second active profile.
        DB::statement("create unique index character_profiles_one_active on character_profiles (status) where status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_profiles');
    }
};
