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
            description: "Unpacking the target out of a container param — a method takes `(Workflow \$workflow, string \$nodeId)` and resolves `\$workflow->graph->nodeById(\$nodeId)`, then works on the target while the container is only packaging"
        );
    }
}
