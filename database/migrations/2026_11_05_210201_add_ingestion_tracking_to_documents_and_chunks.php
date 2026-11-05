<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('embedding_model')->nullable()->after('checksum');
        });

        Schema::table('chunks', function (Blueprint $table): void {
            $table->vector('draft_embedding', 1536)->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('chunks', function (Blueprint $table): void {
            $table->dropColumn('draft_embedding');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('embedding_model');
        });
    }
};
