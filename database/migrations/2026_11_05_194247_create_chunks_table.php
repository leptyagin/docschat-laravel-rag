<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('heading')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->text('content');
            $table->unsignedInteger('token_count');
            $table->vector('embedding', 1536)->nullable();
            $table->tsvector('tsv')->storedAs(
                "setweight(to_tsvector('english', coalesce(heading, '')), 'A') || setweight(to_tsvector('english', content), 'B')",
            );

            $table->index(['document_id', 'position']);
            $table->index('bot_id');
            $table->vectorIndex('embedding');
            $table->index('tsv', algorithm: 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
