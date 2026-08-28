<?php

use Webkul\Inventory\Models\Lot;
use Webkul\Support\Database\Dialects\DatabaseDialect;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/*
 * The 2026-08 sync routes driver-specific SQL through a DatabaseDialect
 * resolved from the container, and upstream ships MySQL and Postgres only —
 * their CI matrix dropped SQLite. Our suite runs on SQLite, so without a
 * binding of our own all 34 tests die at SupportServiceProvider:154 before
 * asserting anything.
 *
 * The server runs MySQL; this exists only to keep the suite runnable on a
 * developer machine. Every method mirrors MySQL's behaviour, so a test that
 * passes here means what it would mean on the server.
 */

it('resolves a dialect on sqlite instead of refusing the driver', function () {
    expect(db_dialect())->toBeInstanceOf(DatabaseDialect::class);
});

it('matches a lot name case-insensitively, the way MySQL collation does', function () {
    // MoveLine resolves lot_name -> Lot through exactly this expression, and
    // SQLite's default BINARY collation would miss where MySQL matches.
    $lot = Lot::factory()->create(['name' => 'MP-POLLO-2606']);

    $found = Lot::query()
        ->whereRaw(db_dialect()->caseInsensitiveEquals('name'), ['mp-pollo-2606'])
        ->first();

    expect($found?->id)->toBe($lot->id);
});

it('buckets a timestamp into YYYY-MM for group-by-month reporting', function () {
    $bucket = DB::query()
        ->selectRaw(db_dialect()->monthBucket("'2026-08-27 13:45:00'").' as bucket')
        ->value('bucket');

    expect($bucket)->toBe('2026-08');
});
