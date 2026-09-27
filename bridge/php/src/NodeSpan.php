<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

/**
 * Where a written node sits in its file, `[start, end)` in bytes, by its id.
 */
final readonly class NodeSpan
{
    public function __construct(public int $start, public int $end, public int $id) {}
}
