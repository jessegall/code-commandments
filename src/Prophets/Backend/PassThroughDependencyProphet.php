<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a constructor-injected dependency that is ONLY ever forwarded, unchanged, to
 * a SINGLE collaborator — never used for its own methods. The class is a needless
 * conduit: inject the dependency at the collaborator instead.
 *
 * In-class usage census (no call graph needed — a private promoted dep's every use
 * is visible in its own class): the property is passed as an argument to calls on
 * one `$this-><collab>` and is NEVER the receiver of a `$this->dep->...()` call.
 * Backward-origin family of #163. ADVISORY (a WARNING). GENERIC: pure AST.
 */
#[IntroducedIn('2.16.0')]
class PassThroughDependencyProphet extends PhpCommandment
{
    private const SCALAR_OR_PSEUDO = [
        'bool', 'int', 'float', 'string', 'array', 'iterable', 'object', 'mixed',
        'callable', 'self', 'static', 'parent', 'void', 'never', 'null', 'true', 'false',
    ];

    public function description(): string
    {
        return 'A dependency only forwarded to one collaborator, never used itself, should be injected there';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A constructor-injected PRIVATE dependency is used ONLY by being passed, '
                . 'unchanged, as an argument to calls on a SINGLE collaborator '
                . '(`$this->collab->m(..., $this->dep, ...)`), and is NEVER the receiver of '
                . 'a `$this->dep->...()` call. The class just relays it.'
            )
            ->leaveWhen(
                'the dependency is used DIRECTLY (any `$this->dep->method()`); it is '
                . 'forwarded to MULTIPLE distinct collaborators; it is held for a lifecycle/'
                . 'identity reason; it is exposed via a getter / returned; or it is unused.'
            )
            ->whenUnsure(
                'if this class never uses the dependency itself and only hands it to one '
                . 'collaborator, inject it at that collaborator (or construct the collaborator '
                . 'with it) and drop the relay parameter — fewer hops, less coupling.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A dependency a class injects but only ever FORWARDS — unchanged — to a single
collaborator, never calling anything on it itself, is a needless conduit. The
class declares a need it does not have; the collaborator could receive the
dependency directly.

Bad — `$this->clock` is only passed along to `$this->scheduler`, never used here:
    public function __construct(private Clock $clock, private Scheduler $scheduler) {}
    public function run(): void { $this->scheduler->at($this->clock, $task); }

Good — give the collaborator the dependency, drop the relay:
    public function __construct(private Scheduler $scheduler) {}   // Scheduler injects Clock itself
    public function run(): void { $this->scheduler->run($task); }

WHAT FIRES — a private, constructor-promoted, OBJECT-typed dependency whose every
in-class use is being passed as an argument to calls on ONE `$this->collab`, and
which is never the receiver of its own method call.

WHAT DOES NOT — a dependency used directly (`$this->dep->m()`), forwarded to several
collaborators, returned/exposed, or unused. Advisory (a WARNING); not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($this->injectedDeps($class) as $dep) {
                $collab = $this->soleForwardingCollaborator($class, $dep, $finder);

                if ($collab !== null) {
                    $warnings[] = $this->warningAt(
                        $class->getMethod('__construct')?->getStartLine() ?? $class->getStartLine(),
                        sprintf(
                            'The injected dependency `$%s` is only ever forwarded, unchanged, to `$this->%s` — it is never used for its own methods here. This class is a needless conduit for it. Inject `$%s` at the `%s` collaborator (or construct it with `$%s`) and drop the relay dependency — fewer hops, less coupling.',
                            $dep,
                            $collab,
                            $dep,
                            $collab,
                            $dep,
                        ),
                        null,
                        'pass-through-dependency:' . $dep,
                    );
                }
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Private, constructor-promoted, object-typed parameter names — the injected deps.
     *
     * @return list<string>
     */
    private function injectedDeps(Node\Stmt\Class_ $class): array
    {
        $ctor = $class->getMethod('__construct');

        if ($ctor === null) {
            return [];
        }

        $deps = [];

        foreach ($ctor->params as $param) {
            $isPromotedPrivate = ($param->flags & Node\Stmt\Class_::MODIFIER_PRIVATE) !== 0;

            if (! $isPromotedPrivate || ! $param->var instanceof Node\Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            if ($this->isObjectType($param->type)) {
                $deps[] = $param->var->name;
            }
        }

        return $deps;
    }

    /**
     * The single `$this-><collab>` property the dependency is always forwarded to, or
     * null when it is used directly, forwarded to >1 collaborator, unused, or used in
     * any unrecognised position (conservative).
     */
    private function soleForwardingCollaborator(Node\Stmt\Class_ $class, string $dep, NodeFinder $finder): ?string
    {
        $uses = 0;          // every `$this->dep` occurrence outside the constructor
        $forwards = 0;      // those that are a call argument
        $collaborators = [];

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct' || $method->stmts === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Node\Expr\PropertyFetch::class) as $fetch) {
                if (! $this->isThisProp($fetch, $dep)) {
                    continue;
                }

                $uses++;
            }

            foreach ($finder->findInstanceOf($method->stmts, Node\Expr\MethodCall::class) as $call) {
                // Direct use: the dependency is the receiver of its own method call.
                if ($this->isThisProp($call->var, $dep)) {
                    return null;
                }

                if ($call->isFirstClassCallable()) {
                    continue; // `$x->m(...)` carries no real arguments
                }

                foreach ($call->getArgs() as $arg) {
                    if ($this->isThisProp($arg->value, $dep)) {
                        $forwards++;
                        $collaborators[$this->receiverKey($call->var)] = true;
                    }
                }
            }
        }

        // Every use must be a forwarding argument (no return/assignment/other), to
        // exactly one collaborator that is a `$this-><collab>` property.
        if ($uses < 1 || $forwards !== $uses || count($collaborators) !== 1) {
            return null;
        }

        $collab = array_key_first($collaborators);

        return str_starts_with((string) $collab, 'prop:') ? substr((string) $collab, 5) : null;
    }

    private function isThisProp(Node $node, string $name): bool
    {
        return $node instanceof Node\Expr\PropertyFetch
            && $node->var instanceof Node\Expr\Variable
            && $node->var->name === 'this'
            && $node->name instanceof Node\Identifier
            && $node->name->toString() === $name;
    }

    /** A stable key for a call receiver — `prop:<name>` for `$this->name`, else `other`. */
    private function receiverKey(Node\Expr $receiver): string
    {
        if ($receiver instanceof Node\Expr\PropertyFetch
            && $receiver->var instanceof Node\Expr\Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Node\Identifier
        ) {
            return 'prop:' . $receiver->name->toString();
        }

        return 'other';
    }

    private function isObjectType(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        return $type instanceof Node\Name && ! in_array(strtolower($type->getLast()), self::SCALAR_OR_PSEUDO, true);
    }
}
