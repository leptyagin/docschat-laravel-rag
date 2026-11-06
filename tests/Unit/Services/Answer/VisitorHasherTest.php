<?php

declare(strict_types=1);

use App\Services\Answer\VisitorHasher;

function hasher(string $key = 'secret'): VisitorHasher
{
    return new VisitorHasher($key);
}

it('keeps different IPv4 addresses apart', function (): void {
    expect(hasher()->hashNetwork('203.0.113.7'))->not->toBe(hasher()->hashNetwork('203.0.113.8'));
});

it('groups every address of one IPv6 /48', function (): void {
    expect(hasher()->hashNetwork('2001:db8:1:2::1'))->toBe(hasher()->hashNetwork('2001:db8:1:ff00:ffff:ffff:ffff:ffff'));
});

it('keeps different IPv6 networks apart', function (): void {
    expect(hasher()->hashNetwork('2001:db8:1::1'))->not->toBe(hasher()->hashNetwork('2001:db8:2::1'));
});

it('recognises one IPv6 address in its different spellings', function (): void {
    expect(hasher()->hashNetwork('2001:0DB8:0001:0000:0000:0000:0000:0001'))->toBe(hasher()->hashNetwork('2001:db8:1::1'));
});

it('does not lump IPv4 visitors together when they arrive IPv4-mapped', function (): void {
    expect(hasher()->hashNetwork('::ffff:203.0.113.7'))->not->toBe(hasher()->hashNetwork('::ffff:203.0.113.8'));
});

it('cannot be reversed without the key', function (): void {
    expect(hasher('one')->hashNetwork('203.0.113.7'))->not->toBe(hasher('two')->hashNetwork('203.0.113.7'));
});

it('accepts a missing address', function (): void {
    expect(hasher()->hashNetwork(null))->toBe(hasher()->hashNetwork(''));
});

it('groups the neighbours of an IPv4 address into one subnet', function (): void {
    expect(hasher()->hashSubnet('203.0.113.7'))->toBe(hasher()->hashSubnet('203.0.113.200'))
        ->and(hasher()->hashSubnet('203.0.113.7'))->not->toBe(hasher()->hashSubnet('203.0.114.7'));
});

it('keeps a subnet apart from a network of the same address', function (): void {
    expect(hasher()->hashSubnet('203.0.113.0'))->not->toBe(hasher()->hashNetwork('203.0.113.0'));
});
