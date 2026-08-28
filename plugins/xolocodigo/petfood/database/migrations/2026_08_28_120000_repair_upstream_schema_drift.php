<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs schema drift left by upstream editing migrations we had already run.
 *
 * `migrate` never re-runs a recorded migration, so when the 2026-08 sync edited
 * six existing ones, a fresh install got the new definitions while our server
 * kept the old — silently, and invisibly to a suite whose database is always
 * built from the current files. `petfood:migration-drift` flagged all six on the
 * server; four turned out to be portability rewrites that produce an identical
 * MySQL schema, and these two are the real differences.
 *
 * Idempotent, and a no-op on a fresh install where the columns are already
 * right, so it is safe on every environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Upstream turned morphs('causer') into nullableMorphs('causer'): a
        // system-generated chatter message has no causer, and inserting one
        // fails against the NOT NULL columns an older database still carries.
        if ($this->columnIsNotNullable('chatter_messages', 'causer_type')) {
            Schema::table('chatter_messages', function (Blueprint $table) {
                $table->string('causer_type')->nullable()->change();
                $table->unsignedBigInteger('causer_id')->nullable()->change();
            });
        }

        // Upstream gave these two columns defaults; without them an insert that
        // omits either one fails on a database created before the edit.
        if ($this->columnLacksDefault('accounts_journals', 'invoice_reference_type')) {
            Schema::table('accounts_journals', function (Blueprint $table) {
                $table->string('invoice_reference_type')->default('invoice')->comment('Communication Type')->change();
                $table->string('invoice_reference_model')->default('aureus')->comment('Communication Standard')->change();
            });
        }
    }

    public function down(): void
    {
        // Deliberately empty: reverting would restore the drift this repairs.
    }

    protected function columnIsNotNullable(string $table, string $column): bool
    {
        return ($this->column($table, $column)['nullable'] ?? true) === false;
    }

    protected function columnLacksDefault(string $table, string $column): bool
    {
        $found = $this->column($table, $column);

        return $found !== null && $found['default'] === null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function column(string $table, string $column): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        foreach (Schema::getColumns($table) as $found) {
            if ($found['name'] === $column) {
                return $found;
            }
        }

        return null;
    }
};
