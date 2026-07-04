<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * The value-flow (provenance) graph — the third whole-program index, alongside the AST and the call
 * graph ({@see CodebaseIndex}). Where {@see Support\TypeResolver} answers "what type is this
 * expression", ValueFlow answers "where does this VALUE go" — it follows a field's value forward
 * through the program (assignments, arguments, returns, into other objects' fields) to every place it
 * is finally consumed, and reports whether those consumptions ASSUME the value is present or
 * ACKNOWLEDGE it can be null ({@see FlowVerdict}).
 *
 * Built once per {@see Codebase} and cached on it (like `index()`); {@see warm} builds the field-read
 * index eagerly so a forked worker inherits it copy-on-write. The forward walk is CONSERVATIVE by
 * construction — an edge it can't resolve is dropped, never guessed — so incompleteness makes a
 * caller MISS a finding, never invent one.
 *
 * Phase 1 (this class) resolves the intra-procedural flow: a field's own reads and the locals they
 * are assigned to. The inter-procedural edges (arg→param, return→call site, field-write→field,
 * closures) attach at the marked seam in {@see follow}.
 */
final class ValueFlow
{
    private readonly TypeResolver $types;

    /** @var array<string, array<string, list<NodeMatch>>>|null  fqcn => field => its read occurrences */
    private ?array $fieldReads = null;

    /** @var array<string, FlowVerdict>  memoised verdicts, keyed "fqcn::field" */
    private array $verdicts = [];

    public function __construct(private readonly Codebase $codebase)
    {
        $this->types = TypeResolver::forCodebase($codebase);
    }

    /**
     * Build the field-read index now, so it exists before a fork and is inherited copy-on-write.
     */
    public function warm(): self
    {
        $this->fieldReads();

        return $this;
    }

    /**
     * How the value of $fqcn::$field is consumed across the whole program.
     */
    public function verdict(string $fqcn, string $field): FlowVerdict
    {
        $fqcn = ltrim($fqcn, '\\');

        return $this->verdicts["{$fqcn}::{$field}"] ??= $this->walk($this->fieldReads()[$fqcn][$field] ?? []);
    }

    /**
     * Walk forward from a set of value occurrences, tallying presence-assumptions vs null-guards. A
     * worklist over occurrences with a visited set (cycle-safe): each occurrence is a terminal
     * (guard / assume) or a propagation whose downstream occurrences are enqueued.
     *
     * @param  list<NodeMatch>  $starts
     */
    private function walk(array $starts): FlowVerdict
    {
        $assume = 0;
        $guard = 0;
        $queue = $starts;
        $seen = [];

        while ($queue !== []) {
            $occurrence = array_pop($queue);
            $node = $occurrence->node;

            if ($node === null || isset($seen[spl_object_id($node)])) {
                continue;
            }

            $seen[spl_object_id($node)] = true;

            if ($occurrence->isNullGuardedUse()) {
                $guard++;
            } elseif ($this->isDereferenced($node)) {
                $assume++;
            } else {
                foreach ($this->follow($occurrence) as $downstream) {
                    $queue[] = $downstream;
                }
            }
        }

        return new FlowVerdict($assume, $guard);
    }

    /**
     * The occurrences a value flows to from $occurrence — the propagation edges. Phase 1: assignment
     * to a local (`$y = <value>`) → the reads of `$y`. Phase 2 attaches arg→param, return→call site,
     * and field-write→field here.
     *
     * @return list<NodeMatch>
     */
    private function follow(NodeMatch $occurrence): array
    {
        $node = $occurrence->node;
        $parent = $node?->getAttribute('parent');

        if (! $parent instanceof Assign || $parent->expr !== $node || ! $parent->var instanceof Variable) {
            return [];
        }

        // The local now carries the value — follow its reads (its writes are not consumptions).
        $followers = [];

        foreach (new NodeMatch($parent->var, $occurrence->file, $this->codebase)->trace() as $interaction) {
            if (! $interaction->isWrite()) {
                $followers[] = $interaction->node;
            }
        }

        return $followers;
    }

    /**
     * Is $node dereferenced as if present — `<value>->prop`, `<value>->method()`, `<value>[i]`? A
     * nullsafe `?->` is NOT a dereference; it is a guard, handled by {@see AstNode::isNullGuardedUse}.
     */
    private function isDereferenced(Node $node): bool
    {
        $parent = $node->getAttribute('parent');

        return ($parent instanceof PropertyFetch || $parent instanceof MethodCall || $parent instanceof ArrayDimFetch)
            && $parent->var === $node;
    }

    /**
     * Every property-fetch read in the tree, bucketed by the class its receiver resolves to and the
     * field name — so a value's flow can start from "all reads of C::$f". Receivers are typed by the
     * chain resolver; an unresolvable one is dropped (never a false owner).
     *
     * @return array<string, array<string, list<NodeMatch>>>
     */
    private function fieldReads(): array
    {
        if ($this->fieldReads !== null) {
            return $this->fieldReads;
        }

        $reads = [];
        $finder = new NodeFinder();

        foreach ($this->codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, PropertyFetch::class) as $fetch) {
                if (! $fetch->name instanceof Identifier) {
                    continue;
                }

                $function = $this->enclosingFunction($fetch);

                if ($function === null) {
                    continue;
                }

                $owner = $this->types->typeOf($fetch->var, $function, $this->enclosingClass($function));

                if ($owner !== null) {
                    $reads[$owner][$fetch->name->toString()][] = new NodeMatch($fetch, $file, $this->codebase);
                }
            }
        }

        return $this->fieldReads = $reads;
    }

    private function enclosingFunction(Node $node): ?FunctionLike
    {
        for ($current = $node->getAttribute('parent'); $current !== null; $current = $current->getAttribute('parent')) {
            if ($current instanceof FunctionLike) {
                return $current;
            }
        }

        return null;
    }

    private function enclosingClass(FunctionLike $function): ?string
    {
        for ($current = $function->getAttribute('parent'); $current !== null; $current = $current->getAttribute('parent')) {
            if ($current instanceof Class_) {
                return ltrim(($current->namespacedName ?? null)?->toString() ?? '', '\\') ?: null;
            }
        }

        return null;
    }
}
