<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use PhpParser\Node;

/**
 * A parsed file's structural context — its node list together with the `use` import map and namespace needed to resolve short type names to FQCNs.
 */
final readonly class FileAst
{
    /**
     * @param  array<Node>  $nodes
     * @param  array<string, string>  $uses  alias => FQCN
     */
    public function __construct(
        public array $nodes,
        public array $uses,
        public ?string $namespace,
    ) {}

    /**
     * @param  array<Node>  $nodes
     */
    public static function of(array $nodes): self
    {
        return new self($nodes, FileImports::of($nodes), FileImports::namespace($nodes));
    }

    /** Resolve a short type name to its FQCN against this file's imports + namespace. */
    public function resolveType(string $typeName): string
    {
        return ltrim(NameResolver::resolve($typeName, $this->uses, $this->namespace), '\\');
    }
}
