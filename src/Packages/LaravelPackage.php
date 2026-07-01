<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;

/**
 * Laravel (and Laravel MCP): teaches the engine that an HTTP/MCP request is a framework boundary,
 * so general structural rules exempt a method that takes one — without themselves knowing Laravel
 * exists. The request bases live once, on {@see LaravelNode}; this package just surfaces them as a
 * cross-detector fact.
 */
final class LaravelPackage extends Package
{
    public function boundaryTypes(): array
    {
        return LaravelNode::REQUEST_TYPES;
    }
}
