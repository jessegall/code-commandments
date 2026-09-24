<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\PassTheObject;

final class ParamResolvedFromParam extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-param-resolved-from-param',
            skill: PassTheObject::class,
            description: 'a method that takes a container and a key and first resolves one against the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — when it only wanted what the key names',
            rule: 'Take the object the method works on, not an id plus the container it lives in; the caller resolves it once and owns the not-found failure.',
            suggestion: 'Change the signature to take the resolved object (`Rename(Node node, string title)`) and resolve at the caller, where the id was born.',
        );
    }
}
