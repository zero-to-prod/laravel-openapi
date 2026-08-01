<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Console;

use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi as Specification;
use Illuminate\Console\Command;
use Throwable;
use ZeroToProd\LaravelOpenapi\SchemaGenerator;

class ValidateSchemaCommand extends Command
{
    protected $signature = 'openapi:validate';

    protected $description = 'Validate the generated OpenAPI document against the OpenAPI specification';

    public function handle(SchemaGenerator $generator): int
    {
        if (!class_exists(Reader::class)) {
            $this->components->error(
                'Validation requires devizzent/cebe-php-openapi. Install it with '
                .'`composer require --dev devizzent/cebe-php-openapi`.'
            );

            return self::FAILURE;
        }

        $document = $generator->document();

        try {
            $specification = Reader::readFromJson(json_encode($document, JSON_THROW_ON_ERROR));
        } catch (Throwable $Throwable) {
            $this->components->error('The generated document could not be read: '.$Throwable->getMessage());

            return self::FAILURE;
        }

        $errors = $this->validate($specification);

        if ($errors !== []) {
            $this->components->error(sprintf('The generated document is not a valid OpenAPI %s document.', $document['openapi'] ?? '3.0'));
            $this->components->bulletList($errors);

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'The generated document is a valid OpenAPI %s document (%d paths).',
            $document['openapi'] ?? '3.0',
            count($document['paths'] ?? []),
        ));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function validate(Specification $specification): array
    {
        $errors = [];

        try {
            $specification->resolveReferences(new ReferenceContext($specification, '/'));
        } catch (UnresolvableReferenceException $e) {
            $errors[] = $e->getMessage();
        }

        $specification->validate();

        return [...$errors, ...$specification->getErrors()];
    }
}
