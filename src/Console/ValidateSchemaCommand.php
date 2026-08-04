<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Console;

use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Throwable;
use ZeroToProd\LaravelOpenapi\Internal\DeclaredPaths;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

/** @internal */
class ValidateSchemaCommand extends Command
{
    private const array operations = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    protected $signature = 'openapi:validate';

    protected $description = 'Validate the generated OpenAPI document against the OpenAPI specification';

    public function handle(SchemaGenerator $SchemaGenerator): int
    {
        // @codeCoverageIgnoreStart
        if (! class_exists(Reader::class)) {
            $this->components->error(
                'Validation requires devizzent/cebe-php-openapi. Install it with '
                .'`composer require --dev devizzent/cebe-php-openapi`.'
            );

            return self::FAILURE;
        }
        // @codeCoverageIgnoreEnd

        $document = $SchemaGenerator->document();

        // The security and declared-path checks read the raw array and need
        // nothing from cebe, so they run even when the reader gives up.
        // Reporting everything at once is the point of this command: a document
        // with a structural fault and a dangling scheme should cost one run to
        // diagnose, not two.
        $declaredPaths = $this->declaredPaths($SchemaGenerator, $document);
        $errors = [...$this->securityErrors($document), ...$declaredPaths['errors']];

        try {
            $specification = Reader::readFromJson(json_encode($document, JSON_THROW_ON_ERROR));
            $errors = [...$errors, ...$this->validate($specification)];
        } catch (Throwable $Throwable) {
            $errors[] = 'The generated document could not be read: '.$Throwable->getMessage();
        }

        $version = is_string($document['openapi'] ?? null) ? $document['openapi'] : '3.0';

        if ($declaredPaths['skipped'] !== null) {
            $this->components->warn($declaredPaths['skipped']);
        }

        if ($errors !== []) {
            $this->components->error(sprintf('The generated document is not a valid OpenAPI %s document.', $version));
            $this->components->bulletList($errors);

            return self::FAILURE;
        }

        $paths = is_array($document['paths'] ?? null) ? $document['paths'] : [];

        $this->components->info(sprintf(
            'The generated document is a valid OpenAPI %s document (%d paths).',
            $version,
            count($paths),
        ));

        return self::SUCCESS;
    }

    /**
     * A declared path is a string in an attribute, and nothing reconciles it
     * with the route the attribute sits on. This does, so it can newly fail a
     * build that passes today; `openapi.validation.declared_paths` is the way
     * out for a document deliberately decoupled from its routes.
     *
     * @param  array<string, mixed>  $document
     * @return array{errors: list<string>, skipped: string|null}
     */
    private function declaredPaths(SchemaGenerator $SchemaGenerator, array $document): array
    {
        if (! Config::boolean('openapi.validation.declared_paths', true)) {
            return ['errors' => [], 'skipped' => null];
        }

        $servers = $document['servers'] ?? null;

        return DeclaredPaths::check(
            $SchemaGenerator->inventory(),
            is_array($servers) ? array_values($servers) : [],
        );
    }

    /** @return list<string> */
    private function validate(OpenApi $OpenApi): array
    {
        $errors = [];

        try {
            $OpenApi->resolveReferences(new ReferenceContext($OpenApi, '/'));
        } catch (UnresolvableReferenceException $e) {
            $errors[] = $e->getMessage();
        }

        $OpenApi->validate();

        return array_values([...$errors, ...$OpenApi->getErrors()]);
    }

    /**
     * A security requirement names a scheme; it does not `$ref` one. So
     * resolveReferences() walks straight past it and validate() does not
     * cross-check it either, and a document naming a scheme nobody declared
     * passes this command. Every request then fails at test time instead, with
     * league's `Mentioned security scheme ... not found in the given spec`.
     *
     * The wording below matches league's deliberately, so a search for the
     * message an agent is staring at finds this check too.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function securityErrors(array $document): array
    {
        $components = $document['components'] ?? null;
        $declared = is_array($components) && is_array($components['securitySchemes'] ?? null)
            ? array_keys($components['securitySchemes'])
            : [];

        $errors = [];

        foreach ($this->requirements($document) as [$scheme, $location]) {
            if (! in_array($scheme, $declared, true)) {
                $errors[] = sprintf(
                    "Mentioned security scheme '%s' not found in the given spec (referenced by %s).",
                    $scheme,
                    $location,
                );
            }
        }

        return $errors;
    }

    /**
     * Every scheme name the document requires, paired with where it asked.
     *
     * @param  array<string, mixed>  $document
     * @return list<array{0: string, 1: string}>
     */
    private function requirements(array $document): array
    {
        $requirements = $this->schemes($document['security'] ?? null, 'the document-level security requirement');
        $paths = $document['paths'] ?? null;

        if (! is_array($paths)) {
            return $requirements;
        }

        foreach ($paths as $path => $item) {
            if (! is_array($item)) {
                continue;
            }

            // Matching on the operation allowlist rather than skipping known
            // non-operations keeps `parameters`, `servers` and any `x-` key out
            // without having to enumerate what else a Path Item may hold.
            foreach (self::operations as $method) {
                $operation = $item[$method] ?? null;

                if (is_array($operation)) {
                    $requirements = [
                        ...$requirements,
                        ...$this->schemes($operation['security'] ?? null, sprintf('%s %s', $method, $path)),
                    ];
                }
            }
        }

        return $requirements;
    }

    /** @return list<array{0: string, 1: string}> */
    private function schemes(mixed $security, string $location): array
    {
        if (! is_array($security)) {
            return [];
        }

        $schemes = [];

        foreach ($security as $requirement) {
            // An empty requirement object is the OpenAPI idiom for "auth is
            // optional here". It names no scheme, so there is nothing to
            // resolve and nothing to complain about.
            if (! is_array($requirement)) {
                continue;
            }

            foreach (array_keys($requirement) as $scheme) {
                $schemes[] = [(string) $scheme, $location];
            }
        }

        return $schemes;
    }
}
