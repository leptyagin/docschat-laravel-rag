<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

it('responds on the health check endpoint', function (): void {
    get('/up')->assertOk();
});

it('runs on PostgreSQL with the pgvector extension', function (): void {
    $extension = DB::selectOne("select extname from pg_extension where extname = 'vector'");

    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and($extension?->extname)->toBe('vector');
});
