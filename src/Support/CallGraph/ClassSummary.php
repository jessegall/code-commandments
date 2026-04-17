<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * Per-class info retained by the CodebaseIndex.
 *
 * `propertyTypes` combines constructor-promoted properties and declared
 * typed properties into a single `propName => FQCN` map, used when
 * resolving `$this->prop->method()` calls back to a concrete class.
 */
final readonly class ClassSummary
{
    /**
     * @param  array<string, string>  $useStatements  alias => FQCN
     * @param  array<string, string>  $propertyTypes  propName => FQCN
     * @param  array<string, MethodSummary>  $methods
     */
    public function __construct(
        public string $fqcn,
        public ?string $parent,
        public array $useStatements,
        public array $propertyTypes,
        public array $methods,
        public string $filePath,
    ) {}
}
