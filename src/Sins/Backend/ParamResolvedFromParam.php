<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\PassTheObject;

final class ParamResolvedFromParam extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'param-resolved-from-param',
            skill: PassTheObject::class,
            description: 'Unpacking the target out of a container parameter — a method takes `(Workflow $workflow, string $nodeId)` and resolves `$workflow->graph->nodeById($nodeId)` itself, when it could just receive the node directly.',
            rule: "Demand the resolved object you need; don't take a container + key and unpack the target yourself — the caller resolves once and passes it.",
            suggestion: "Take the resolved object as the param; resolve once in the caller."
        );
    }
}
