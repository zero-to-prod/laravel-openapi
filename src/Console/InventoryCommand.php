<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Console;

use Illuminate\Console\Command;
use JsonException;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
class InventoryCommand extends Command
{
    protected $signature = 'openapi:inventory
        {--json : Emit the inventory as JSON rather than a listing}
        {--document : Emit the merged OpenAPI document as JSON}';

    protected $description = 'List every registered route and the schema its handler declares';

    /** @throws JsonException */
    public function handle(SchemaGenerator $SchemaGenerator): int
    {
        if ($this->option('document')) {
            $this->output->writeln(json_encode($SchemaGenerator->document(), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $inventory = $SchemaGenerator->inventory();

        if ($this->option('json')) {
            $this->output->writeln(json_encode($inventory, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        foreach ($inventory as $entry) {
            $methods = $entry['methods'];

            $this->output->writeln(sprintf(
                '%s %s %s — %s',
                $entry['documented'] ? '[x]' : '[ ]',
                implode('|', $methods),
                $entry['uri'],
                $entry['action'] ?? 'closure',
            ));
        }

        return self::SUCCESS;
    }
}
