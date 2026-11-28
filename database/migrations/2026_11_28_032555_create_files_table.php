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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind');
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamp('kept_at')->nullable();
            $table->string('extraction')->default('ready');
            $table->text('extraction_error')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->text('body');
            $table->char('content_hash', 64);
            $table->string('author');
            $table->foreignId('message_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->char('operation_hash', 64)->nullable();
            $table->text('summary');
            $table->timestamp('created_at');

            $table->unique(['file_id', 'number']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('file_versions')->nullOnDelete();
        });

        Schema::create('file_subjects', function (Blueprint $table) {
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->primary(['file_id', 'subject_type', 'subject_id']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('file_message', function (Blueprint $table) {
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->index()->constrained()->cascadeOnDelete();
            $table->string('role');

            $table->primary(['file_id', 'message_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_message');
        Schema::dropIfExists('file_subjects');

        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('files');
    }
};
