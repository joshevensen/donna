<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();
    }

    /**
     * Reverse the migrations.
     *
     * The extension is left in place: dropping it would take every vector
     * column with it, and other databases on the server may share it.
     */
    public function down(): void
    {
        //
    }
};
