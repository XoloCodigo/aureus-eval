<?php

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/*
 * Guard for schema drift between this database and the migration files.
 *
 * `migrate` never re-runs a migration it has already recorded, so when upstream
 * EDITS an existing migration (the 2026-08 sync edits six, one of them turning
 * chatter's causer columns nullable), a fresh install gets the new definition
 * and a long-lived database keeps the old one. The whole test suite is blind to
 * this by construction: it runs on a database built fresh from the current
 * files, which is the side that is never wrong.
 *
 * Only a check against the real target database can see it, so this is a
 * deploy-time gate rather than an assertion about our code.
 */

it('fails when an edited migration has already been applied to this database', function () {
    // Applied here by the ERP install, so editing its file upstream means this
    // database and the file have silently diverged.
    $this->artisan('petfood:migration-drift', [
        'migrations' => ['2024_12_23_062355_create_chatter_messages_table'],
    ])->assertExitCode(1);
});

it('passes when the edited migrations have not been applied to this database', function () {
    // Never applied here, so `migrate` will run the current file as-is.
    $this->artisan('petfood:migration-drift', [
        'migrations' => ['2099_01_01_000000_create_something_that_never_ran_table'],
    ])->assertExitCode(0);
});
