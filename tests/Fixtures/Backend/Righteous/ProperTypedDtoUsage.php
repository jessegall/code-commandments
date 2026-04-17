<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Righteous;

final readonly class NodeRow
{
    public function __construct(
        public string $nodeId,
        public string $port,
        public string $label,
    ) {}
}

class ProperTypedDtoUsage
{
    public function process(NodeRow $row): string
    {
        return $row->nodeId . ':' . $row->port . ':' . $row->label;
    }
}
