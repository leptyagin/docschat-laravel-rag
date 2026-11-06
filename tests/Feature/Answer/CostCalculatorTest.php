<?php

declare(strict_types=1);

use App\Data\Answer\ModelUsage;
use App\Services\Answer\CostCalculator;
use Illuminate\Support\Facades\Log;

it('charges an unlisted model the high fallback price and logs a warning', function (): void {
    $log = Log::spy();
    $calculator = new CostCalculator([], ['input' => '5.00', 'output' => '25.00']);

    expect($calculator->total([new ModelUsage('unknown-model', 1000, 1000)]))->toBe('0.030000');

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
