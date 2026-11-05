<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 16);
            $table->string('title');
            $table->string('source_url', 2048)->nullable();
            $table->string('file_path')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->jsonb('extraction')->nullable();
            $table->timestamps();

            $table->unique(['bot_id', 'source_url']);
            $table->unique(['bot_id', 'file_path']);
            $table->index(['bot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
