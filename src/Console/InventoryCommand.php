<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Console;

use Illuminate\Console\Command;
use JsonException;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/**
 * Exists so the MCP `status` tool can read the application from a process that
 * booted just now. Reflection cannot see a class edited after it was
 * autoloaded, and routes files are evaluated once at boot, so a long-lived
 * server answers from a snapshot no matter how carefully it re-reflects.
 *
 * @internal
 */
class InventoryCommand extends Command
{
    protected $signature = 'openapi:inventory {--json : Emit the inventory as JSON rather than a listing}';

    protected $description = 'List every registered route and the schema its handler declares';

    /** @throws JsonException */
    public function handle(SchemaGenerator $SchemaGenerator): int
    {
        $inventory = $SchemaGenerator->inventory();

        if ($this->option('json')) {
            // Raw, undecorated, and last: `status` reads the final line of
            // output that parses, so anything the framework prints first is
            // survivable.
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
