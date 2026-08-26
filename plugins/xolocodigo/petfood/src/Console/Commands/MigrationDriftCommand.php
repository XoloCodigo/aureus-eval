<?php

namespace XoloCodigo\PetFood\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Deploy-time gate against schema drift.
 *
 * `migrate` never re-runs a migration it has already recorded. When an upstream
 * sync EDITS a migration this database already ran, a fresh install gets the new
 * definition and this database keeps the old one — silently, and invisibly to a
 * test suite that always builds its database from the current files.
 *
 * Run it on the target database after pulling a sync and before `migrate`:
 *
 *     php artisan petfood:migration-drift --since=<pre-merge SHA>
 *
 * A non-zero exit means those migrations need the change applied by hand.
 */
class MigrationDriftCommand extends Command
{
    protected $signature = 'petfood:migration-drift
        {migrations?* : Migration names to check, without the .php extension}
        {--since= : Git ref to diff against HEAD to find edited migrations}';

    protected $description = 'Fail when a migration this database already ran has since been edited';

    public function handle(): int
    {
        $edited = $this->argument('migrations') ?: $this->editedSince($this->option('since'));

        if (! $edited) {
            $this->components->error('Pass migration names or --since=<git-ref>.');

            return self::INVALID;
        }

        $applied = DB::table('migrations')->whereIn('migration', $edited)->pluck('migration');

        if ($applied->isEmpty()) {
            $this->components->info(count($edited).' edited migration(s), none of them applied here. No drift.');

            return self::SUCCESS;
        }

        $this->components->error($applied->count().' migration(s) already ran here and were edited since — apply the change by hand:');
        $applied->each(fn (string $migration) => $this->line('  '.$migration));

        return self::FAILURE;
    }

    /**
     * Migrations modified (not added) between $ref and HEAD.
     *
     * @return array<int, string>
     */
    protected function editedSince(?string $ref): array
    {
        if (! $ref) {
            return [];
        }

        $result = Process::path(base_path())->run([
            'git', 'diff', '--name-status', '--diff-filter=M', $ref.'..HEAD', '--', '*/database/migrations/*',
        ]);

        if (! $result->successful()) {
            $this->components->error('git diff failed: '.trim($result->errorOutput()));

            return [];
        }

        return collect(preg_split('/\R/', trim($result->output()), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $line) => basename(trim(substr($line, 1)), '.php'))
            ->all();
    }
}
