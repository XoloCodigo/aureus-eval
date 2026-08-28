<?php

namespace XoloCodigo\PetFood\Database\Dialects;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\Support\Database\Dialects\DatabaseDialect;

/**
 * SQLite implementation of upstream's DatabaseDialect.
 *
 * Upstream ships MySQL and Postgres only — their CI matrix dropped SQLite in
 * the 2026-08 sync — while our test suite runs on database/testing.sqlite. This
 * keeps the suite runnable without editing the core: PetFoodServiceProvider
 * rebinds the container singleton when the driver is sqlite.
 *
 * The server runs MySQL, so each method aims at MySqlDialect's behaviour rather
 * than SQLite's most idiomatic form — a test that passes locally should mean
 * what it would mean in production.
 *
 * That parity is close but NOT exact, and the gap is documented per method
 * rather than glossed over: see caseInsensitiveEquals, where SQLite folds only
 * ASCII and MySQL folds Unicode and accents too.
 */
class SqliteDialect implements DatabaseDialect
{
    public function jsonArrayAgg(string $column): string
    {
        return "JSON_GROUP_ARRAY({$column})";
    }

    public function monthBucket(string $column): string
    {
        return "STRFTIME('%Y-%m', {$column})";
    }

    public function alterColumnType(string $table, string $column, string $blueprintMethod, string $postgresType, string $postgresUsing): void
    {
        // SQLite has no ALTER COLUMN TYPE; Laravel's ->change() rebuilds the
        // table instead. Column types are advisory here anyway (dynamic typing),
        // so this matters for the schema a fresh test database reports, not for
        // how values are stored.
        Schema::table($table, function (Blueprint $blueprint) use ($column, $blueprintMethod) {
            $blueprint->{$blueprintMethod}($column)->change();
        });
    }

    public function syncSequences(): void
    {
        // SQLite's rowid/AUTOINCREMENT already advances past explicit inserts,
        // same as MySQL's AUTO_INCREMENT. Nothing to resync.
    }

    public function caseInsensitiveEquals(string $column): string
    {
        // MySQL's utf8mb4_unicode_ci compares case-insensitively; SQLite's
        // default BINARY collation does not, so ask for NOCASE explicitly.
        //
        // Known gap: NOCASE folds ASCII A-Z only, while utf8mb4_unicode_ci also
        // folds Unicode case AND accents. 'PIÑA-01' vs 'piña-01' matches on the
        // server and misses here; so does 'CAFE' vs 'CAFÉ'. Lot names at the
        // plant are ASCII (MP-POLLO-2606), so this has no bite today — but a
        // non-ASCII lot name would make a green test here mean less than it
        // does on MySQL. Verify that case against the server, not locally.
        return "{$column} COLLATE NOCASE = ?";
    }
}
