<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\FeatureEnvy;

/**
 * #142: a Data DTO that builds ITSELF from a source domain object. Its static
 * from-source factory necessarily reads the source's fields to MAP them into this
 * type — that is mapping, not feature envy (moving it onto NodeDescriptor would
 * couple the domain object to this DTO). Must NOT be flagged.
 */
class FromSourceDataMapper
{
    private function __construct(
        public readonly array $handles,
        public readonly bool $hasBody,
    ) {}

    public static function forDescriptor(NodeDescriptor $descriptor): self
    {
        return new self(
            array_merge($descriptor->continuationHandleNames(), $descriptor->bodyHandleNames()),
            $descriptor->bodyHandleNames() !== [],
        );
    }
}
