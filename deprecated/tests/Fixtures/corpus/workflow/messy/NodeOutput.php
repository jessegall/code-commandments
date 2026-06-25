<?php

namespace App\Workflow;

class NodeOutput
{
    public function __construct(
        public string $name,
        public bool $control,
        public ?string $match,
    ) {}
}
