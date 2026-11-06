<?php

declare(strict_types=1);

use App\Services\Answer\VisitorNetwork;

it('treats an IPv4 address as its own network', function (): void {
    expect(new VisitorNetwork()->of('203.0.113.7'))->toBe('203.0.113.7');
});

it('groups the addresses of one IPv6 customer', function (): void {
    $network = new VisitorNetwork;

    expect($network->of('2001:db8:1:2::1'))->toBe($network->of('2001:db8:1:2:ffff:ffff:ffff:ffff'));
});

it('keeps different IPv6 customers apart', function (): void {
    $network = new VisitorNetwork;

    expect($network->of('2001:db8:1:2::1'))->not->toBe($network->of('2001:db8:1:3::1'));
});

it('recognises one IPv6 address in its different spellings', function (): void {
    $network = new VisitorNetwork;

    expect($network->of('2001:0DB8:0001:0002:0000:0000:0000:0001'))->toBe($network->of('2001:db8:1:2::1'));
});

it('does not lump IPv4 visitors together when they arrive IPv4-mapped', function (): void {
    expect(new VisitorNetwork()->of('::ffff:203.0.113.7'))->toBe('203.0.113.7');
});

it('passes through a value that is not an address', function (): void {
    expect(new VisitorNetwork()->of(''))->toBe('');
});
