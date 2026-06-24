<?php

namespace App\Workflow;

/**
 * A declared output of a workflow node.
 */
final class NodeOutput
{
    public function __construct(
        public string $name,
        public bool $control,
        public ?string $match,
    ) {}
}
