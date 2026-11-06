<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table): void {
            $table->jsonb('embed_origins')->default('[]')->after('suggested_questions');
            $table->boolean('documents_public')->default(true)->after('embed_origins');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table): void {
            $table->dropColumn(['embed_origins', 'documents_public']);
        });
    }
};
