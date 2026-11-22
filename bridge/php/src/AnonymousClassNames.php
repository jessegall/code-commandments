<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Gives each anonymous class a symbol of its own, in its namespace, named by its file and place, so its members and
 * the reads of its fields resolve, and its supertypes are known, like a named class's. It keeps no written name.
 */
final class AnonymousClassNames extends NodeVisitorAbstract
{
    private string $namespace = '';

    public function __construct(private readonly string $file) {}

    public function enterNode(Node $node): null
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name?->toString() ?? '';
        }
        if ($node instanceof Stmt\Class_ && $node->name === null) {
            $place = 'class@anonymous@'.$this->file.':'.$node->getStartFilePos();
            $node->namespacedName = new Node\Name($this->namespace === '' ? $place : $this->namespace.'\\'.$place);
        }

        return null;
    }
}
