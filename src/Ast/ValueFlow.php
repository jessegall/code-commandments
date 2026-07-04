<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * The value-flow (provenance) graph — the third whole-program index, alongside the AST and the call
 * graph ({@see CodebaseIndex}). Where {@see Support\TypeResolver} answers "what type is this
 * expression", ValueFlow answers "where does this VALUE go" — it follows a field's value forward
 * through the program to every place it is finally consumed, and reports whether those consumptions
 * ASSUME the value is present or ACKNOWLEDGE it can be null ({@see FlowVerdict}).
 *
 * The forward walk crosses function boundaries: a field read assigned to a local, passed as an
 * argument (into the callee's parameter), returned (out to every call site), or written into another
 * object's field — each is an edge to follow, until the value reaches a terminal that decides
 * nil-vs-non-nil. A guard ANYWHERE in that closure means the field is legitimately optional.
 *
 * Built once per {@see Codebase} and cached on it (like `index()`); {@see warm} builds the field-read
 * index eagerly for copy-on-write fork sharing. CONSERVATIVE by construction — an edge it can't
 * resolve is dropped, never guessed — so incompleteness makes a caller MISS a finding, never invent
 * one. Termination: a visited-node set plus a visited-SLOT set (so a value cycling through fields
 * expands each slot once).
 */
final class ValueFlow
{
    /** A backstop against a pathological walk — far above any real field's fan-out. */
    private const int MAX_STEPS = 5000;

    private readonly TypeResolver $types;

    /** @var array<string, array<string, list<NodeMatch>>>|null  fqcn => field => its read occurrences */
    private ?array $fieldReads = null;

    /** @var array<string, \JesseGall\CodeCommandments\Ast\ParsedFile>|null  fqcn => the file it's declared in */
    private ?array $fileForClass = null;

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
     * worklist over occurrences: each is a terminal (guard / assume) or a propagation whose downstream
     * occurrences are enqueued. A visited-node set and a visited-slot set keep it finite.
     *
     * @param  list<NodeMatch>  $starts
     */
    private function walk(array $starts): FlowVerdict
    {
        $assume = 0;
        $guard = 0;
        $queue = $starts;
        $seenNodes = [];
        $seenSlots = [];
        $steps = 0;

        while ($queue !== [] && $steps++ < self::MAX_STEPS) {
            $occurrence = array_pop($queue);
            $node = $occurrence->node;

            if ($node === null || isset($seenNodes[spl_object_id($node)])) {
                continue;
            }

            $seenNodes[spl_object_id($node)] = true;

            if ($occurrence->isNullGuardedUse()) {
                $guard++;
            } elseif ($this->isDereferenced($node) || $this->flowsToNonNullableParam($occurrence)) {
                $assume++;
            } else {
                foreach ($this->follow($occurrence, $seenSlots) as $downstream) {
                    $queue[] = $downstream;
                }
            }
        }

        return new FlowVerdict($assume, $guard);
    }

    /**
     * The occurrences a value flows to from $occurrence — the propagation edges: assignment to a
     * local, an argument into a NULLABLE parameter (a non-nullable one is a terminal assume, above),
     * a `return` out to every call site, and a write into another object's field.
     *
     * @param  array<string, true>  $seenSlots
     * @return list<NodeMatch>
     */
    private function follow(NodeMatch $occurrence, array &$seenSlots): array
    {
        return [
            ...$this->viaAssignment($occurrence),
            ...$this->viaArgument($occurrence, $seenSlots),
            ...$this->viaReturn($occurrence, $seenSlots),
            ...$this->viaFieldWrite($occurrence, $seenSlots),
        ];
    }

    /**
     * `$y = <value>` → the reads of the local `$y` (its writes are not consumptions).
     *
     * @return list<NodeMatch>
     */
    private function viaAssignment(NodeMatch $occurrence): array
    {
        $parent = $occurrence->node?->getAttribute('parent');

        if (! $parent instanceof Assign || $parent->expr !== $occurrence->node || ! $parent->var instanceof Variable) {
            return [];
        }

        return $this->readsOf($parent->var->name, $this->enclosingFunction($occurrence->node), $occurrence->file);
    }

    /**
     * `f(<value>)` where the target parameter is NULLABLE → follow into the callee: the parameter's
     * reads, plus — when the parameter is promoted — the reads of the field it becomes. (A
     * non-nullable target is a terminal assume, handled in {@see walk}.)
     *
     * @param  array<string, true>  $seenSlots
     * @return list<NodeMatch>
     */
    private function viaArgument(NodeMatch $occurrence, array &$seenSlots): array
    {
        $node = $occurrence->node;
        $parent = $node?->getAttribute('parent');

        if (! $parent instanceof Arg) {
            return [];
        }

        $target = $this->targetParam($parent->getAttribute('parent'), $parent);

        if ($target === null) {
            return [];
        }

        [$fqcn, $method, $param] = $target;
        $name = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : null;

        if ($name === null || ! $this->markSlot("P:{$fqcn}::{$method}#{$name}", $seenSlots)) {
            return [];
        }

        $downstream = $this->readsOf($name, $this->methodNode($fqcn, $method), $this->fileForClass()[$fqcn] ?? null);

        // A promoted parameter also becomes the object's field — follow that slot too.
        if ($param->flags !== 0) {
            $downstream = [...$downstream, ...$this->fieldSlotReads($fqcn, $name, $seenSlots)];
        }

        return $downstream;
    }

    /**
     * `return <value>` → the value flows out to every resolved call site of the enclosing method.
     *
     * @param  array<string, true>  $seenSlots
     * @return list<NodeMatch>
     */
    private function viaReturn(NodeMatch $occurrence, array &$seenSlots): array
    {
        $node = $occurrence->node;
        $parent = $node?->getAttribute('parent');
        $function = $this->enclosingFunction($node);

        if (! $parent instanceof Return_ || $parent->expr !== $node || ! $function instanceof ClassMethod) {
            return [];
        }

        $fqcn = $this->enclosingClass($function);
        $method = $function->name->toString();

        if ($fqcn === null || ! $this->markSlot("R:{$fqcn}::{$method}", $seenSlots)) {
            return [];
        }

        return $this->codebase->index()->callersOf($fqcn, $method);
    }

    /**
     * `$this->g = <value>` → the value becomes this object's field `g`; follow that field's reads.
     * (The `new Y(g: <value>)` field-write is reached through {@see viaArgument}'s promoted branch.)
     *
     * @param  array<string, true>  $seenSlots
     * @return list<NodeMatch>
     */
    private function viaFieldWrite(NodeMatch $occurrence, array &$seenSlots): array
    {
        $node = $occurrence->node;
        $parent = $node?->getAttribute('parent');

        if (! $parent instanceof Assign || $parent->expr !== $node || ! $parent->var instanceof PropertyFetch) {
            return [];
        }

        $target = $parent->var;

        if (! $target->name instanceof Identifier) {
            return [];
        }

        $owner = $this->types->typeOf($target->var, $this->enclosingFunction($node), $this->enclosingClassOf($node));

        return $owner === null ? [] : $this->fieldSlotReads($owner, $target->name->toString(), $seenSlots);
    }

    /**
     * Is $occurrence a direct argument landing on a NON-nullable parameter — a sink that forbids
     * null, so the value must be present where it flows?
     */
    private function flowsToNonNullableParam(NodeMatch $occurrence): bool
    {
        $node = $occurrence->node;
        $parent = $node?->getAttribute('parent');

        if (! $parent instanceof Arg) {
            return false;
        }

        $target = $this->targetParam($parent->getAttribute('parent'), $parent);

        return $target !== null && TypeName::isNullable($target[2]->type) === false && $target[2]->type !== null;
    }

    /**
     * Resolve which parameter an argument lands on — by name for a named argument, by position
     * otherwise — as `[calleeFqcn, method, Param]`, or null when the callee/param can't be resolved.
     *
     * @return array{0: string, 1: string, 2: Param}|null
     */
    private function targetParam(?Node $call, Arg $arg): ?array
    {
        $callee = $this->callee($call);

        if ($callee === null) {
            return null;
        }

        [$fqcn, $method] = $callee;
        $params = array_values($this->methodNode($fqcn, $method)?->params ?? []);

        if ($params === []) {
            return null;
        }

        if ($arg->name instanceof Identifier) {
            foreach ($params as $param) {
                if ($param->var instanceof Variable && $param->var->name === $arg->name->toString()) {
                    return [$fqcn, $method, $param];
                }
            }

            return null;
        }

        $position = $this->positionOf($call, $arg);
        $param = $position === null ? null : ($params[$position] ?? null);

        return $param === null ? null : [$fqcn, $method, $param];
    }

    /**
     * The class + method a call dispatches to — a `$typed->method()`, `Class::method()`, or
     * `new Class(...)` (→ `__construct`). Null when the receiver/class can't be resolved.
     *
     * @return array{0: string, 1: string}|null
     */
    private function callee(?Node $call): ?array
    {
        if ($call instanceof MethodCall && $call->name instanceof Identifier) {
            $type = $this->types->typeOf($call->var, $this->enclosingFunction($call), $this->enclosingClassOf($call));

            return $type === null ? null : [$type, $call->name->toString()];
        }

        if ($call instanceof StaticCall && $call->class instanceof Name && $call->name instanceof Identifier) {
            return [ltrim($call->class->toString(), '\\'), $call->name->toString()];
        }

        if ($call instanceof New_ && $call->class instanceof Name) {
            return [ltrim($call->class->toString(), '\\'), '__construct'];
        }

        return null;
    }

    private function positionOf(?Node $call, Arg $arg): ?int
    {
        $args = ($call instanceof MethodCall || $call instanceof StaticCall || $call instanceof New_) ? $call->args : [];

        foreach ($args as $position => $candidate) {
            if ($candidate === $arg) {
                return $arg->unpack ? null : $position;
            }
        }

        return null;
    }

    /**
     * The reads of the field $fqcn::$field, guarded so a slot expands at most once per walk.
     *
     * @param  array<string, true>  $seenSlots
     * @return list<NodeMatch>
     */
    private function fieldSlotReads(string $fqcn, string $field, array &$seenSlots): array
    {
        return $this->markSlot("F:{$fqcn}::{$field}", $seenSlots) ? ($this->fieldReads()[$fqcn][$field] ?? []) : [];
    }

    /**
     * The read occurrences of a named variable in a function body — skipping its writes and its own
     * parameter declaration.
     *
     * @return list<NodeMatch>
     */
    private function readsOf(?string $name, ?FunctionLike $function, ?ParsedFile $file): array
    {
        if ($name === null || $function === null || $file === null) {
            return [];
        }

        $reads = [];

        foreach (new NodeFinder()->findInstanceOf([$function], Variable::class) as $variable) {
            if ($variable->name !== $name || $variable->getAttribute('parent') instanceof Param) {
                continue;
            }

            $match = new NodeMatch($variable, $file, $this->codebase);

            if ($match->interactionKind() !== InteractionKind::Assigned) {
                $reads[] = $match;
            }
        }

        return $reads;
    }

    private function methodNode(string $fqcn, string $method): ?ClassMethod
    {
        $class = $this->codebase->classNamed($fqcn)->node;

        return $class instanceof Class_ ? $class->getMethod($method) : null;
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
     * field name. Receivers are typed by the chain resolver; an unresolvable one is dropped.
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
                $owner = $function === null ? null : $this->types->typeOf($fetch->var, $function, $this->enclosingClass($function));

                if ($owner !== null) {
                    $reads[$owner][$fetch->name->toString()][] = new NodeMatch($fetch, $file, $this->codebase);
                }
            }
        }

        return $this->fieldReads = $reads;
    }

    /**
     * @return array<string, ParsedFile>  fqcn => the file that declares it
     */
    private function fileForClass(): array
    {
        if ($this->fileForClass !== null) {
            return $this->fileForClass;
        }

        $map = [];
        $finder = new NodeFinder();

        foreach ($this->codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                $name = ($class->namespacedName ?? null)?->toString();

                if ($name !== null) {
                    $map[ltrim($name, '\\')] = $file;
                }
            }
        }

        return $this->fileForClass = $map;
    }

    /**
     * Mark a slot visited; returns false if it was already seen (so the caller skips re-expanding it).
     *
     * @param  array<string, true>  $seenSlots
     */
    private function markSlot(string $slot, array &$seenSlots): bool
    {
        if (isset($seenSlots[$slot])) {
            return false;
        }

        $seenSlots[$slot] = true;

        return true;
    }

    private function enclosingClassOf(Node $node): ?string
    {
        $function = $this->enclosingFunction($node);

        return $function === null ? null : $this->enclosingClass($function);
    }

    private function enclosingFunction(?Node $node): ?FunctionLike
    {
        for ($current = $node?->getAttribute('parent'); $current !== null; $current = $current->getAttribute('parent')) {
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
