<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\PassTheObject;

final class ParamResolvedFromParam extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-param-resolved-from-param',
            skill: PassTheObject::class,
            description: 'a function that takes a container and a key and first resolves one against the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — when it only wanted what the key names',
            rule: 'Take the object the function works on, not an id plus the container it lives in; the caller resolves it once and owns the not-found failure.',
            suggestion: 'Change the signature to take the resolved object (`def rename(node: Node, title: str)`) and resolve at the caller, where the id was born.',
        );
    }
}
