<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Packages\Tags\ArrayReturning;
use JesseGall\CodeCommandments\Packages\Tags\Boundary;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * Laravel (and Laravel MCP): teaches the engine which of its types are framework boundaries,
 * contract hooks, config classes, and no-container casts — so general structural rules exempt them
 * without knowing Laravel exists. The FQCNs live once, on {@see LaravelNode}.
 */
final class LaravelPackage extends Package
{
    private const string FORM_REQUEST = 'Illuminate\\Foundation\\Http\\FormRequest';

    private const string MCP_REQUEST = 'Laravel\\Mcp\\Request';

    private const string MCP_TOOL = 'Laravel\\Mcp\\Server\\Tool';

    private const string MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    public function register(Exemptions $exemptions): void
    {
        // Entry points: a request is where raw input crosses in — don't move behaviour onto it,
        // and a method taking one is a boundary that may unpack it.
        $exemptions->exempt(Boundary::class)->classes(...LaravelNode::REQUEST_TYPES);

        // Contract hooks: a per-subclass array the framework mandates, its shape not the author's.
        $exemptions->exempt(ContractMethod::class)
            ->on(self::FORM_REQUEST, 'rules')
            ->on(self::MCP_REQUEST, 'rules')
            ->on(self::MCP_TOOL, 'rules', 'schema')
            ->on(self::MODEL, 'casts');

        // Config classes whose whole job is returning arrays (rules/messages/attributes/schema/…) —
        // exempt wholesale, robust to hooks a rule can't enumerate. (A Model is NOT here — only its
        // casts() above; a Model returning an array elsewhere is a real bag.)
        $exemptions->exempt(ArrayReturning::class)->classes(self::FORM_REQUEST, self::MCP_REQUEST, self::MCP_TOOL);

        // No-container: Eloquent builds a cast itself, no DI — a loose array param is the convention.
        $exemptions->exempt(NoContainer::class)->classes(...LaravelNode::CAST_CONTRACTS);
    }
}
