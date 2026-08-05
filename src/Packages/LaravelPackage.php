<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Packages\Tags\ArrayReturning;
use JesseGall\CodeCommandments\Packages\Tags\Association;
use JesseGall\CodeCommandments\Packages\Tags\Boundary;
use JesseGall\CodeCommandments\Packages\Tags\CompositionRoot;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * Laravel (and Laravel MCP): teaches the engine which of its types are framework boundaries,
 * contract hooks, config classes, and no-container casts — so general structural rules exempt them
 * without knowing Laravel exists. The FQCNs live once, on {@see LaravelNode}.
 */
final class LaravelPackage extends Package
{
    public function register(Exemptions $exemptions): void
    {
        // Entry points: a request is where raw input crosses in — don't move behaviour onto it,
        // and a method taking one is a boundary that may unpack it.
        $exemptions->exempt(Boundary::class)->classes(...LaravelNode::REQUEST_TYPES);

        // Contract hooks: a per-subclass array the framework mandates, its shape not the author's.
        $exemptions->exempt(ContractMethod::class)
            ->on(LaravelNode::FORM_REQUEST, 'rules')
            ->on(LaravelNode::MCP_REQUEST, 'rules')
            ->on(LaravelNode::MCP_TOOL, 'rules', 'schema')
            ->on(LaravelNode::MODEL, 'casts');

        // Config classes whose whole job is returning arrays (rules/messages/attributes/schema/…) —
        // exempt wholesale, robust to hooks a rule can't enumerate. A Model earns only the narrower
        // `casts()` contract above; its array returns elsewhere stay a real bag.
        $exemptions->exempt(ArrayReturning::class)->classes(LaravelNode::FORM_REQUEST, LaravelNode::MCP_REQUEST, LaravelNode::MCP_TOOL);

        // No-container: Eloquent builds a cast itself, no DI — a loose array param is the convention.
        $exemptions->exempt(NoContainer::class)->classes(...LaravelNode::CAST_CONTRACTS);

        // Associations: a relation names the class at the OTHER end, and Eloquent requires both ends to
        // name each other — so neither reference carries a direction, and the pair is not a cycle.
        $exemptions->exempt(Association::class)->methods(...LaravelNode::RELATION_METHODS);

        // The same binding stated as an ATTRIBUTE: `#[ObservedBy(OrderObserver::class)]` is answered by
        // the observer naming the model, and the framework mandates both ends — replacing it with a
        // registration elsewhere is not the same behaviour, so the reference draws no arrow either.
        $exemptions->exempt(Association::class)->attributes(...LaravelNode::BINDING_ATTRIBUTES);

        // Composition root: a service provider's register/boot wires config() into the typed objects it
        // binds — config reads there are the sanctioned composition root, not a config-in-a-class smell.
        $exemptions->exempt(CompositionRoot::class)->classes(LaravelNode::SERVICE_PROVIDER);
    }
}
