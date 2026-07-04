<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
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
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
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

        if (! isset($this->verdicts["{$fqcn}::{$field}"])) {
            $terminals = $this->terminals($this->fieldReads()[$fqcn][$field] ?? []);
            $this->verdicts["{$fqcn}::{$field}"] = new FlowVerdict(count($terminals['assume']), count($terminals['guard']));
        }

        return $this->verdicts["{$fqcn}::{$field}"];
    }

    /**
     * The `path:line`s of the reads that made $fqcn::$field's verdict — a calibration aid answering
     * "WHY was this flagged / cleared". Returned as `['assume' => [...], 'guard' => [...]]`.
     *
     * @return array{assume: list<string>, guard: list<string>}
     */
    public function explain(string $fqcn, string $field): array
    {
        $terminals = $this->terminals($this->fieldReads()[ltrim($fqcn, '\\')][$field] ?? []);

        return [
            'assume' => array_map(static fn (NodeMatch $m): string => $m->location(), $terminals['assume']),
            'guard' => array_map(static fn (NodeMatch $m): string => $m->location(), $terminals['guard']),
        ];
    }

    /**
     * The DECIDING chain of $fqcn::$field as a list of `edgeKind@file` steps — the path from the field
     * to its deepest ASSUME terminal (the read that makes it a phantom). Each step names how the value
     * arrived (`read`, `assign`, `arg`, `return`, `field`) and in which file, so a caller reads both
     * how DEEP the chain goes (its distinct files) and IN WHAT KIND (its edges). Measured to the
     * deciding terminal, so a decoy branch can't pad it, and empty when the field is not a phantom.
     *
     * @return list<string>
     */
    public function chainPath(string $fqcn, string $field): array
    {
        $deepest = [];

        foreach ($this->terminals($this->fieldReads()[ltrim($fqcn, '\\')][$field] ?? [])['chains'] as $chain) {
            if (self::files($chain) > self::files($deepest)) {
                $deepest = $chain;
            }
        }

        return $deepest;
    }

    /**
     * The number of distinct files a `kind@file` step list crosses.
     *
     * @param  list<string>  $chain
     */
    private static function files(array $chain): int
    {
        return count(array_unique(array_map(static fn (string $step): string => explode('@', $step, 2)[1], $chain)));
    }

    /**
     * Walk forward from a set of value occurrences, collecting the terminals: each occurrence is a
     * guard, an assume, or a propagation whose downstream occurrences are enqueued. A visited-node
     * set and a visited-slot set keep it finite.
     *
     * Each queue item carries its arrival EDGE KIND and the ordered `kind@file` STEPS from the start,
     * so an assume terminal records its own chain — depth (distinct files) and kind (edges) both —
     * immune to a decoy branch elsewhere.
     *
     * @param  list<NodeMatch>  $starts
     * @return array{assume: list<NodeMatch>, guard: list<NodeMatch>, chains: list<list<string>>}
     */
    private function terminals(array $starts): array
    {
        $assume = [];
        $guard = [];
        $chains = [];
        $queue = array_map(static fn (NodeMatch $start): array => [$start, 'read', [], null], $starts);
        $seen = [];
        $seenSlots = [];
        $steps = 0;

        while ($queue !== [] && $steps++ < self::MAX_STEPS) {
            [$occurrence, $kind, $path, $key] = array_pop($queue);
            $node = $occurrence->node;

            if ($node === null) {
                continue;
            }

            $visit = spl_object_id($node) . ':' . ($key ?? '');

            if (isset($seen[$visit])) {
                continue;
            }

            $seen[$visit] = true;
            $path[] = $kind . '@' . basename($occurrence->file->path);

            // A carrier (the value is inside an array under $key) is never itself a guard or a deref —
            // only the value it eventually yields is. A direct value ($key === null) is classified.
            if ($key === null && $occurrence->isNullGuardedUse()) {
                $guard[] = $occurrence;
            } elseif ($key === null && ($this->isDereferenced($node) || $this->flowsToNonNullableParam($occurrence))) {
                $assume[] = $occurrence;
                $chains[] = $path;
            } else {
                foreach ($this->follow($occurrence, $key, $seenSlots) as [$downstream, $edge, $nextKey]) {
                    $queue[] = [$downstream, $edge, $path, $nextKey];
                }
            }
        }

        return ['assume' => $assume, 'guard' => $guard, 'chains' => $chains];
    }

    /**
     * The occurrences a value flows to from $occurrence, each tagged with its EDGE KIND and the array
     * KEY it now sits under (null = a bare value). A bare value flows by assignment, argument, return,
     * and field-write — and can be put INTO an array. A carrier (value inside an array under $key)
     * flows the array the same ways, and yields the value back out at an element read or a `::from()`.
     *
     * @param  array<string, true>  $seenSlots
     * @return list<array{0: NodeMatch, 1: string, 2: ?string}>
     */
    private function follow(NodeMatch $occurrence, ?string $key, array &$seenSlots): array
    {
        $moved = [
            ...$this->keyed($this->viaAssignment($occurrence), 'assign', $key),
            ...$this->keyed($this->viaArgument($occurrence, $seenSlots), 'arg', $key),
            ...$this->keyed($this->viaReturn($occurrence, $seenSlots), 'return', $key),
        ];

        if ($key === null) {
            return [
                ...$moved,
                ...$this->keyed($this->viaFieldWrite($occurrence, $seenSlots), 'field', null),
                ...$this->viaArrayInsertion($occurrence),
            ];
        }

        return [
            ...$moved,
            ...$this->viaArrayElement($occurrence, $key),
            ...$this->keyed($this->viaFromArray($occurrence, $key, $seenSlots), 'from', null),
        ];
    }

    /**
     * `[<value>]` / `['k' => <value>]` → the array that now carries the value, keyed by its literal key
     * (or `*` for an index / dynamic key). From here the CARRIER flows.
     *
     * @return list<array{0: NodeMatch, 1: string, 2: ?string}>
     */
    private function viaArrayInsertion(NodeMatch $occurrence): array
    {
        $item = $occurrence->node?->getAttribute('parent');

        if (! $item instanceof ArrayItem || $item->value !== $occurrence->node) {
            return [];
        }

        $array = $item->getAttribute('parent');

        if (! $array instanceof Array_) {
            return [];
        }

        return [[new NodeMatch($array, $occurrence->file, $this->codebase), 'array', $this->literalKey($item)]];
    }

    /**
     * The value coming back OUT of the array this carrier holds: an element read (`$arr['k']`,
     * `$arr[0]`), a `foreach ($arr as $v)`, or a spread (`[...$arr]`) that re-carries it.
     *
     * @return list<array{0: NodeMatch, 1: string, 2: ?string}>
     */
    private function viaArrayElement(NodeMatch $occurrence, string $key): array
    {
        $node = $occurrence->node;
        $parent = $node?->getAttribute('parent');

        if ($parent instanceof ArrayDimFetch && $parent->var === $node) {
            return $this->keyMatches($parent->dim, $key) ? [[new NodeMatch($parent, $occurrence->file, $this->codebase), 'element', null]] : [];
        }

        if ($parent instanceof Foreach_ && $parent->expr === $node && $parent->valueVar instanceof Variable && is_string($parent->valueVar->name)) {
            return $this->keyed($this->readsOf($parent->valueVar->name, $this->enclosingFunction($node), $occurrence->file), 'element', null);
        }

        // `[...$arr]` — the spread re-carries the element into the surrounding array, key intact.
        if ($parent instanceof ArrayItem && $parent->unpack && $parent->getAttribute('parent') instanceof Array_) {
            return [[new NodeMatch($parent->getAttribute('parent'), $occurrence->file, $this->codebase), 'spread', $key]];
        }

        return [];
    }

    /**
     * `SomeData::from([... 'k' => <value> ...])` — the array this carrier holds is hydrated into an
     * object; the value at key $key becomes that class's field `$key`. The shortcut across the vendor
     * `from()` (a chain detector is allowed to reason through it): follow into the field's reads.
     *
     * @param  array<string, true>  $seenSlots
     * @return list<NodeMatch>
     */
    private function viaFromArray(NodeMatch $occurrence, string $key, array &$seenSlots): array
    {
        $arg = $occurrence->node?->getAttribute('parent');

        if (! $arg instanceof Arg || $key === '*') {
            return [];
        }

        $call = $arg->getAttribute('parent');

        if (! $call instanceof StaticCall || ! $call->class instanceof Name || ! $call->name instanceof Identifier || ! str_starts_with($call->name->toString(), 'from')) {
            return [];
        }

        return $this->fieldSlotReads(ltrim($call->class->toString(), '\\'), $key, $seenSlots);
    }

    /**
     * The literal string/int key an array item sits under, or `*` when it has none or a dynamic one.
     */
    private function literalKey(ArrayItem $item): string
    {
        return match (true) {
            $item->key instanceof String_ => $item->key->value,
            $item->key instanceof Int_ => (string) $item->key->value,
            default => '*',
        };
    }

    private function keyMatches(?Node $dim, string $key): bool
    {
        if ($key === '*') {
            return true;
        }

        return ($dim instanceof String_ && $dim->value === $key) || ($dim instanceof Int_ && (string) $dim->value === $key);
    }

    /**
     * Tag each occurrence with the edge kind that reached it and the key it now carries.
     *
     * @param  list<NodeMatch>  $downstream
     * @return list<array{0: NodeMatch, 1: string, 2: ?string}>
     */
    private function keyed(array $downstream, string $edge, ?string $key): array
    {
        return array_map(static fn (NodeMatch $match): array => [$match, $edge, $key], $downstream);
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

        return $target !== null && ! TypeResolver::paramAcceptsNull($target[2]);
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
