<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use JesseGall\CodeCommandments\Ast\Support\ExpressionType;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;

/**
 * A matched node with its file — an {@see AstNode} that knows where it is.
 * Subclass to add domain predicates; type-hint the subclass in a `where` closure
 * and the query will inject it without registration.
 */
class NodeMatch extends AstNode implements Located
{
    public function __construct(
        Node $node,
        public readonly ParsedFile $file,
        public readonly Codebase $codebase,
    ) {
        parent::__construct($node);
    }

    /**
     * Does this arrow function's return type only restate what its expression provably yields? The
     * comparison is exact — same spelling, same nullability — and unproven means false, never true.
     * {@see \JesseGall\CodeCommandments\Ast\Support\ExpressionType}
     */
    public function returnTypeRestatesItsExpression(): bool
    {
        if (! $this->node instanceof ArrowFunction || $this->node->returnType === null) {
            return false;
        }

        $declared = TypeName::render($this->node->returnType);

        if (in_array(strtolower($declared), ['self', 'static', 'parent'], true)) {
            return false; // What these mean depends on where the closure ends up, not on the expression.
        }

        $yielded = ExpressionType::of($this->codebase, $this->node->expr, $this->enclosingClassName());

        return $yielded !== null && $yielded === $declared;
    }

    /**
     * Is this method's NAME inherited rather than chosen — declared by a parent class or an interface,
     * ours or a package's? Renaming it would break the contract, so a naming rule has nothing to say.
     * {@see Codebase::overridesMethod} answers for both (the parsed graph, then reflection).
     */
    public function nameIsInherited(): bool
    {
        $method = $this->methodName();

        return $method !== null && $this->codebase->overridesMethod($this->enclosingClassName(), $method);
    }

    /**
     * Is this node inside PHP's serialization-protocol boundary — the body of
     * `__unserialize()` / `__set_state()`, whose raw array-bag parameter the LANGUAGE
     * dictates, or a method whose EVERY call site sits inside one (a private helper the
     * hook hands its bag to)? String-indexing there is the canonical deserialization
     * parse point; it cannot take a value object instead.
     */
    public function isWithinSerializationBoundary(): bool
    {
        return $this->scopeIsSerializationBoundary($this->enclosingClassName(), $this->enclosingFunctionName(), 0);
    }

    private function scopeIsSerializationBoundary(?string $class, ?string $method, int $depth): bool
    {
        if (in_array($method, ['__unserialize', '__set_state'], true)) {
            return true;
        }

        if ($class === null || $method === null || $depth >= 3) {
            return false;
        }

        $callers = $this->codebase->index()->callersOf($class, $method);

        return $callers !== [] && array_all(
            $callers,
            fn (NodeMatch $caller) => $this->scopeIsSerializationBoundary(
                $caller->enclosingClassName(),
                $caller->enclosingFunctionName(),
                $depth + 1,
            ),
        );
    }

    /**
     * For a `match` whose `default` returns `null`: do the HANDLED arms themselves already
     * admit null — an arm body calling a method DECLARED `?T`? Then null is part of the
     * match's ANSWER VOCABULARY (the recognised cases can answer "no answer" too), and the
     * default gives the unrecognised rest that same declared answer — it is NOT a swallowed
     * unhandled case. Suppressed when the subject is an enum: a closed set has a finite
     * case list, so its default hides a hole regardless of what the arms return.
     */
    public function matchHandledArmsAdmitNull(): bool
    {
        if (! $this->node instanceof Match_ || ! $this->matchDefaultIsNull() || $this->matchSubjectIsEnum()) {
            return false;
        }

        foreach ($this->node->arms as $arm) {
            if ($arm->conds !== null && $this->declaredReturnAdmitsNull($arm->body)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this match's `default` arm return the `null` literal specifically?
     */
    private function matchDefaultIsNull(): bool
    {
        foreach ($this->node instanceof Match_ ? $this->node->arms : [] as $arm) {
            if ($arm->conds === null) {
                return new AstNode($arm->body)->isNull();
            }
        }

        return false;
    }

    /**
     * Does the match subject resolve to an enum type — a genuinely CLOSED value set?
     */
    private function matchSubjectIsEnum(): bool
    {
        $function = $this->enclosingFunction();

        if (! $this->node instanceof Match_ || $function === null) {
            return false;
        }

        $subject = TypeResolver::forCodebase($this->codebase)->typeOf($this->node->cond, $function, $this->enclosingClassName());

        return $this->codebase->isEnum($subject);
    }

    /**
     * Is this expression a call to a method whose DECLARED return type is nullable?
     */
    private function declaredReturnAdmitsNull(Node $expr): bool
    {
        if (AstNode::isMethodSend($expr)) {
            $receiver = ReceiverResolver::typeOf(new self($expr, $this->file, $this->codebase));

            return $expr->name instanceof Identifier
                && $this->codebase->methodReturnsNullable($receiver, $expr->name->toString());
        }

        if ($expr instanceof StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier) {
            $class = in_array($expr->class->toString(), ['self', 'static', 'parent'], true)
                ? $this->enclosingClassName()
                : $expr->class->toString();

            return $this->codebase->methodReturnsNullable($class, $expr->name->toString());
        }

        return false;
    }

    /**
     * The ONE type every argument of this call is asked of — `render($order->isPaid(), $order->total())`
     * answers with the Order's FQCN, because both arguments are answers the same object gave. Null when
     * an argument asks nothing (a literal, a bare variable, a `new`) or when the arguments disagree about
     * whose answers they carry.
     *
     * The "did the caller already hold the object?" question, answered once for a whole call site: if it
     * did, the object itself is the honest argument.
     */
    public function argumentSubjectType(): ?string
    {
        $arguments = $this->arguments();
        $function = $this->enclosingFunction();

        if ($arguments === [] || $function === null) {
            return null;
        }

        $resolver = TypeResolver::forCodebase($this->codebase);
        $self = $this->enclosingClassName();
        $types = [];

        foreach ($arguments as $argument) {
            $asked = new NodeFinder()->find([$argument->value], static fn (Node $node): bool =>
                AstNode::isMethodSend($node) || $node instanceof PropertyFetch);

            if ($asked === []) {
                return null;
            }

            foreach ($asked as $ask) {
                $types[$resolver->typeOf($ask->var, $function, $self) ?? ''] = true;
            }
        }

        return count($types) === 1 && ! isset($types['']) ? array_key_first($types) : null;
    }

    /**
     * The 1-based line where this match begins.
     */
    public function line(): int
    {
        return $this->node->getStartLine();
    }

    /**
     * The path of the file this match sits in — the {@see Located} half that the frontend's
     * {@see \JesseGall\CodeCommandments\Vue\ElementMatch} always had, so anything engine-agnostic
     * (the fixture harness, the runner) can read a finding's file without knowing which engine
     * produced it.
     */
    public function file(): string
    {
        return $this->file->path;
    }

    /**
     * The match's `path:line`, the form a finding is reported as.
     */
    public function location(): string
    {
        return "{$this->file->path}:{$this->line()}";
    }

    /**
     * This match's position AS a {@see Span} — the seam the scribe layer rewrites
     * through, the backend mirror of {@see \JesseGall\CodeCommandments\Vue\ElementMatch::span()}.
     * php-parser end offsets are INCLUSIVE; a {@see Span} end is EXCLUSIVE, hence `+ 1`.
     */
    public function span(): Span
    {
        return new Span(
            $this->file->path,
            $this->file->source,
            $this->node->getStartFilePos(),
            $this->node->getEndFilePos() + 1,
        );
    }

    /**
     * How a CONTROL STRUCTURE opens in this match's file — `' {'` (K&R) or `"\n{$indent}{"` (Allman),
     * sampled from the first `if`/`foreach`/`for`/`while` the file already contains. The companion to
     * {@see Span::blockOpener} for a scribe that emits a block where NO brace stands today (so it has
     * nothing local to read the style from): a file that puts its braces on their own line gets the
     * same back, and a fix a human has to reformat is only half a fix (#416). K&R when the file holds
     * no control structure to learn from — PHP's own default for one.
     */
    public function controlBlockOpener(string $indent): string
    {
        $sample = $this->controlStructureSample();

        return $sample === null
            ? ' {'
            : Span::blockOpener($this->file->source, $sample->getStartFilePos(), $indent);
    }

    /**
     * Does this match's file stand a control structure's brace on its OWN line (Allman)? The same
     * sample {@see controlBlockOpener} reads, as the question rather than the text — so a scribe
     * emitting a keyword BETWEEN two blocks (an `else`, a `catch`) can place it in the file's style
     * without picking apart the opener string.
     */
    public function controlBracesOnOwnLine(): bool
    {
        $sample = $this->controlStructureSample();

        return $sample !== null && Span::braceOnItsOwnLine($this->file->source, $sample->getStartFilePos());
    }

    /**
     * The first control structure the file contains — whatever a scribe learns the file's block
     * style from. Null when it holds none.
     */
    private function controlStructureSample(): ?Node
    {
        return new NodeFinder()->findFirst(
            $this->file->ast,
            static fn (Node $node): bool => $node instanceof If_
                || $node instanceof Foreach_
                || $node instanceof For_
                || $node instanceof While_,
        );
    }

    /**
     * Trace this variable through its enclosing function: every place it travels
     * to, in source order, each classified as an {@see Interaction}. Returns an
     * empty trace when this match is not a (named) variable.
     *
     * @return list<Interaction>
     */
    public function trace(): array
    {
        if (! $this->node instanceof Variable || ! is_string($this->node->name)) {
            return [];
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return [];
        }

        $interactions = [];

        foreach ((new NodeFinder)->findInstanceOf([$function], Variable::class) as $occurrence) {
            if ($occurrence->name !== $this->node->name) {
                continue;
            }

            $match = new self($occurrence, $this->file, $this->codebase);
            $interactions[] = new Interaction($match, $match->interactionKind());
        }

        return $interactions;
    }

    /**
     * Is this expression's result de-nulled — directly ({@see isDeNulled}), or via
     * the variable it's assigned to anywhere in the function? The assigned-variable
     * case is answered by tracing that variable: if any stop on its journey is a
     * null guard, the result is being checked for absence downstream.
     */
    public function resultIsDeNulled(): bool
    {
        if ($this->isDeNulled()) {
            return true;
        }

        $parent = $this->parent()->node;

        if (! $parent instanceof Assign || ! $parent->var instanceof Variable) {
            return false;
        }

        foreach (new self($parent->var, $this->file, $this->codebase)->trace() as $interaction) {
            if ($interaction->deNulls()) {
                return true;
            }
        }

        return false;
    }

    /**
     * For a method call `$x->m()`: is the receiver variable `$x` mutated by a
     * property write (`$x->prop = …`) elsewhere in the same function? Found by
     * tracing the receiver and looking for a {@see InteractionKind::PropertyWrite}.
     *
     * `$this` is excluded: a model writing its own fields then `$this->save()` is
     * the intention method itself — the right home, not a call-site mutation.
     */
    public function receiverMutatedNearby(): bool
    {
        $receiver = $this->node->var ?? null;

        if (! $receiver instanceof Variable || $receiver->name === 'this') {
            return false;
        }

        foreach (new self($receiver, $this->file, $this->codebase)->trace() as $interaction) {
            if ($interaction->kind === InteractionKind::PropertyWrite) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is there a call to $name within $lines of this match, in the same file?
     */
    public function near(string $name, int $lines = 5): bool
    {
        $line = $this->line();

        foreach ((new NodeFinder)->find($this->file->ast, static fn (Node $n): bool => Calls::name($n) === $name) as $other) {
            if ($other !== $this->node && abs($other->getStartLine() - $line) <= $lines) {
                return true;
            }
        }

        return false;
    }
}
