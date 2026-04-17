<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful;

/**
 * Subscripts the result of a method call. The fix is to have the called method
 * return a DTO instead of a plain array.
 */
class ArrayStringIndexingFromMethodCall
{
    public function __construct(
        private readonly NodeRepository $nodes,
    ) {}

    public function describe(int $nodeId): string
    {
        $node = $this->nodes->find($nodeId);

        return $node['nodeId'] . ' on port ' . $node['port'];
    }
}

class NodeRepository
{
    public function find(int $id): array
    {
        return ['nodeId' => 'n-' . $id, 'port' => 'eth0'];
    }
}
