<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Testing\TestResponse;
use ZeroToProd\LaravelOpenapi\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);
pest()->tia()->locally();

function mcpText(TestResponse $response): string
{
    $content = new ReflectionMethod($response, 'content')->invoke($response);

    return implode("\n", array_filter(is_array($content) ? $content : [], is_string(...)));
}

function withArtisan(?string $script): string
{
    $directory = sys_get_temp_dir().'/openapi-status-base-'.bin2hex(random_bytes(6));

    mkdir($directory, 0755, true);

    if ($script !== null) {
        file_put_contents($directory.'/artisan', $script);
    }

    app()->setBasePath($directory);

    return $directory;
}

function withoutFreshProcess(): void
{
    withArtisan(null);
}

function withRepositoryAsBasePath(): void
{
    app()->setBasePath(dirname(__DIR__));
}

/**
 * @param  list<array<string, mixed>>  $entries
 *
 * @throws JsonException
 */
function withInventory(array $entries): void
{
    withArtisan('<?php echo '.var_export(json_encode($entries, JSON_THROW_ON_ERROR), true).';');
}

function purgeBasePaths(): void
{
    foreach (glob(sys_get_temp_dir().'/openapi-status-base-*') ?: [] as $directory) {
        array_map(unlink(...), glob($directory.'/*') ?: []);
        rmdir($directory);
    }
}
