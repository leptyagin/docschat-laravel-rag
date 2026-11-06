<?php

declare(strict_types=1);

use App\Services\Answer\AnswerSlots;

beforeEach(function (): void {
    config()->set('docschat.answers.max_concurrent', 2);
    config()->set('docschat.answers.max_concurrent_per_network', 1);
});

it('gives one network only its share of the slots', function (): void {
    $slots = app(AnswerSlots::class);

    expect($slots->acquire('network-a'))->not->toBeNull()
        ->and($slots->acquire('network-a'))->toBeNull()
        ->and($slots->acquire('network-b'))->not->toBeNull();
});

it('refuses every network once all slots are taken', function (): void {
    $slots = app(AnswerSlots::class);

    $slots->acquire('network-a');
    $slots->acquire('network-b');

    expect($slots->acquire('network-c'))->toBeNull();
});

it('does not keep the share of a network that found no free slot', function (): void {
    $slots = app(AnswerSlots::class);

    $first = $slots->acquire('network-a');
    $second = $slots->acquire('network-b');

    expect($slots->acquire('network-c'))->toBeNull();

    $first?->release();

    expect($slots->acquire('network-c'))->not->toBeNull();

    $second?->release();
});

it('frees a slot that was released', function (): void {
    $slots = app(AnswerSlots::class);

    $slots->acquire('network-a')?->release();

    expect($slots->acquire('network-a'))->not->toBeNull();
});
