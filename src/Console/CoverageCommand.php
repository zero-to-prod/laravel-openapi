<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Console;

use Illuminate\Console\Command;
use ZeroToProd\LaravelOpenapi\Internal\SchemaCoverage;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
class CoverageCommand extends Command
{
    protected $signature = 'openapi:coverage {--reset : Discard recorded coverage and exit}';

    protected $description = 'Report declared responses that no test exercised';

    public function handle(SchemaGenerator $generator): int
    {
        if ($this->option('reset')) {
            SchemaCoverage::purge();

            $this->components->info(sprintf('Discarded recorded coverage at %s.', SchemaCoverage::path()));

            return self::SUCCESS;
        }

        SchemaCoverage::load();

        $document = $generator->document();
        $declared = SchemaCoverage::declared($document);
        $missing = SchemaCoverage::missing($document);

        if ($declared === []) {
            $this->components->warn('The document declares no responses, so there is nothing to cover.');

            return self::SUCCESS;
        }

        if ($missing === []) {
            $this->components->info(sprintf(
                'Every declared response was exercised (%d of %d).',
                count($declared),
                count($declared),
            ));

            return self::SUCCESS;
        }

        $this->components->error(sprintf(
            '%d of %d declared responses were never exercised.',
            count($missing),
            count($declared),
        ));

        $this->components->bulletList($missing);

        if (SchemaCoverage::exercised() === []) {
            $this->components->warn(sprintf(
                'No coverage was recorded at all. Does the suite call assertMatchesSchema(), and is %s written before this runs?',
                SchemaCoverage::path(),
            ));
        }

        return self::FAILURE;
    }
}
