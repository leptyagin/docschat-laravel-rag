<?php

declare(strict_types=1);

use App\Data\Answer\ModelUsage;
use App\Services\Answer\CostCalculator;
use Illuminate\Support\Facades\Log;

it('counts an unpriced model as free and logs a warning', function (): void {
    $log = Log::spy();

    expect(new CostCalculator([])->total([new ModelUsage('unknown-model', 1000, 1000)]))->toBe('0.000000');

    $log->shouldHaveReceived('warning')->once();
});

test('every default model has a price', function (): void {
    $prices = config('docschat.prices');

    expect($prices)->toHaveKeys([
        config('docschat.ai.chat.model'),
        config('docschat.ai.chat.rewrite_model'),
        config('docschat.ai.embeddings.model'),
    ]);
});
