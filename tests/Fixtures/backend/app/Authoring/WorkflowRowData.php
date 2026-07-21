<?php

namespace Shop\Authoring;

use Spatie\LaravelData\Data;

/**
 * The row a workflow listing renders. `updatedAt` is typed as always-present, which is a promise the
 * writers below cannot keep.
 */
final class WorkflowRowData extends Data
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $trigger,
        public readonly bool $active,
        public readonly string $updatedAt,
    ) {}
}
