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

    public function contractMethods(): array
    {
        return [
            // A request's validation array, an MCP tool's input schema, a model's cast map — each
            // a per-subclass array the framework reads by contract, its shape not the author's to
            // choose. (The request/tool bases are ALSO array-returning types below; naming their
            // hooks here is what near-duplicate reads for the shared-skeleton exemption.)
            'Illuminate\\Foundation\\Http\\FormRequest' => ['rules'],
            'Laravel\\Mcp\\Request' => ['rules'],
            'Laravel\\Mcp\\Server\\Tool' => ['rules', 'schema'],
            'Illuminate\\Database\\Eloquent\\Model' => ['casts'],
        ];
    }

    public function arrayReturningTypes(): array
    {
        // A FormRequest / MCP request / MCP tool exists to hand the framework arrays (rules,
        // messages, attributes, schema, …) — so array-return-bag leaves the whole class alone,
        // robust to hooks it can't enumerate. (A Model is NOT here — only its `casts()` is a
        // contract, above; a Model returning an array elsewhere is a real bag.)
        return [
            'Illuminate\\Foundation\\Http\\FormRequest',
            'Laravel\\Mcp\\Request',
            'Laravel\\Mcp\\Server\\Tool',
        ];
    }

    public function noContainerTypes(): array
    {
        // Eloquent instantiates an attribute cast itself, with no container — the cast contracts
        // live once on LaravelNode.
        return LaravelNode::CAST_CONTRACTS;
    }
}
