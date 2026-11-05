<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->timestamps();

            $table->index(['bot_id', 'visitor_hash']);
            $table->index(['bot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
