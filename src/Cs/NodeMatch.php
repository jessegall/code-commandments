<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\ReadsFunctionBody;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Support\Prose;
use JesseGall\CodeCommandments\Testing\DeclarationMarkers;
use JesseGall\PhpTypes\Option;

/**
 * A C# node a query found, with the file it is in — a statement, a member or an expression alike.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class NodeMatch implements Located
{
    use ReadsFunctionBody;

    /**
     * The types that read as a lookup KEY — an identity, not a collaborator.
     */
    private const array KEY_TYPES = ['global::System.String', 'global::System.Int32', 'global::System.Int64', 'global::System.Guid'];

    public function __construct(
        public readonly Node $node,
        public readonly ModuleFile $module,
    ) {}

    /**
     * The name the node declares or reads — a method's, a class's, an identifier's; an accessor answers
     * for the property, indexer or event it belongs to, as the compiler names it (`get_Total`). Empty for
     * anything nameless.
     */
    public function name(): string
    {
        if ($this->node->name !== null || ! str_ends_with($this->node->kind, 'AccessorDeclaration')) {
            return $this->node->name ?? '';
        }

        foreach ($this->module->ancestorsOf($this->node) as $member) {
            if ($member->is('PropertyDeclaration', 'IndexerDeclaration', 'EventDeclaration') && $member->name !== null) {
                return $member->name;
            }
        }

        return '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->node->start);
    }

    public function file(): string
    {
        return $this->module->file;
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    public function span(): Span
    {
        return $this->module->spanAt($this->node->start, $this->node->end);
    }

    /**
     * What the node IS, named where it has a name — `MethodDeclaration Total`, `ClassDeclaration Order`.
     */
    public function scope(): string
    {
        return $this->name() === '' ? $this->node->kind : "{$this->node->kind} {$this->name()}";
    }

    public function isConstructorDeclaration(): bool
    {
        return $this->node->is('ConstructorDeclaration');
    }

    /**
     * A body that only says it is not written yet — `throw new NotImplementedException()`, as a block or
     * an expression body. (A member with no body at all — abstract, on an interface, a partial's
     * declaring half — is no function to begin with.)
     */
    public function isStub(): bool
    {
        return $this->node->functionBody()->isSomeAnd(
            static fn (Node $body): bool => self::thrown($body)->isSomeAnd(static fn (Node $exception): bool => $exception->type?->name === 'global::System.NotImplementedException'),
        );
    }

    /**
     * Does this member override a base member or implement an interface's, as the compiler resolved it?
     * Its shape is the contract's before it is its own.
     */
    public function isOverride(): bool
    {
        return $this->node->inherited;
    }

    /**
     * What $body throws when throwing is all it does — `{ throw …; }` or `=> throw …`.
     *
     * @return Option<Node>
     */
    private static function thrown(Node $body): Option
    {
        $throw = match (true) {
            $body->is('Block') && count($body->children()) === 1 && $body->children()[0]->is('ThrowStatement') => $body->children()[0],
            $body->is('ArrowExpressionClause') && $body->expressions()[0]->is('ThrowExpression') => $body->expressions()[0],
            default => null,
        };

        return Option::fromNullable($throw?->expressions()[0] ?? null);
    }

    /**
     * Is this an `else if` — an `if` standing as another `if`'s `else`, one rung of its ladder?
     */
    public function isElseIf(): bool
    {
        return $this->node->is('IfStatement') && $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent->is('ElseClause'));
    }

    /**
     * Is this an `if` whose branch already left — it ends in a `return`, `throw`, `continue`, `break` or
     * `yield break` — yet carries an `else`? The `else` says nothing the exit did not, and indents the
     * rest for it. An `if` whose `else` is the next rung is a ladder, not a guard, and is left to the
     * ladder rule.
     */
    public function hasRedundantElse(): bool
    {
        if (! $this->node->is('IfStatement') || $this->isElseIf()) {
            return false;
        }

        [$branch, $else] = [$this->node->children()[0], $this->node->children()[1] ?? null];

        if ($else === null || $else->children()[0]->is('IfStatement')) {
            return false;
        }

        $statements = $branch->is('Block') ? $branch->children() : [$branch];

        return $statements !== [] && end($statements)->isBailOut();
    }

    /**
     * How many rungs this `if` ladder has when every one compares the SAME subject with a constant —
     * `if (kind == Kind.Box) … else if (kind == Kind.Pallet) …` — the subjects compared as fingerprinted
     * expressions. Zero for a ladder whose rungs test anything else, and for an `else if` itself.
     */
    public function subjectLadderLength(): int
    {
        if (! $this->node->is('IfStatement') || $this->isElseIf()) {
            return 0;
        }

        $subjects = [];

        foreach (self::rungs($this->node) as $rung) {
            $subject = $rung->expressions()[0]->comparisonSubject();

            if ($subject->isNone()) {
                return 0;
            }

            $subjects[StructuralHash::ofExpression($subject->unwrap())] = true;
        }

        return count($subjects) === 1 ? count(self::rungs($this->node)) : 0;
    }

    /**
     * The `if` statements of the ladder $if opens — itself, then each `else if` in turn.
     *
     * @return list<Node>
     */
    private static function rungs(Node $if): array
    {
        $else = $if->children()[1] ?? null;
        $next = $else?->children()[0];

        return [$if, ...($next?->is('IfStatement') === true ? self::rungs($next) : [])];
    }

    /**
     * Is this `if` — no `else` — the whole body of a loop, burying real work (two statements or more) a
     * level deep behind its condition? The loop wants `if (!…) continue;`. A one-statement filter stays
     * as it is, and so does a search — a body that ends by leaving the loop picks the one item it wanted.
     */
    public function isSoleLoopBodyGuard(): bool
    {
        if (! $this->node->is('IfStatement') || count($this->node->children()) !== 1) {
            return false;
        }

        $branch = $this->node->children()[0];
        $work = $branch->is('Block') ? $branch->children() : [$branch];

        if (count($work) < 2 || end($work)->isBailOut()) {
            return false;
        }

        $ancestors = $this->module->ancestorsOf($this->node);
        [$holder, $loop] = ($ancestors[0] ?? null)?->is('Block') === true ? [$ancestors[0], $ancestors[1] ?? null] : [null, $ancestors[0] ?? null];

        return $loop?->isLoop() === true && ($holder === null || count($holder->children()) === 1);
    }

    /**
     * Is this expression handed straight to a call as one of its arguments?
     */
    public function fillsArgument(): bool
    {
        return $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent->is('Argument'));
    }

    /**
     * Is this fallback expression — `name ?? ""` — compared with `==` or `!=` against the very value it falls
     * back to, so the fallback only ever cancels itself?
     */
    public function isComparedToItsFallback(): bool
    {
        $fallback = $this->node->fallback();
        $compared = $this->node;
        $parent = $this->module->parentOf($compared);

        while ($parent->isSomeAnd(static fn (Node $around): bool => $around->is('ParenthesizedExpression'))) {
            $compared = $parent->unwrap();
            $parent = $this->module->parentOf($compared);
        }

        return $fallback->isSomeAnd(static fn (Node $value): bool => $parent->isSomeAnd(static fn (Node $comparison): bool => $comparison->is('EqualsExpression', 'NotEqualsExpression')
            && array_any($comparison->children, static fn (Node $side): bool => $side !== $compared && $side->isSameValueAs($value))));
    }

    /**
     * Is this a `throw new …` inside a catch that does not hand the caught exception on — so the original
     * stack trace is lost? A throw in a lambda written inside the catch runs later, outside it, and is
     * not wrapping anything.
     */
    public function isWrappingWithoutCause(): bool
    {
        $created = array_values(array_filter($this->node->expressions(), static fn (Node $thrown): bool => $thrown->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression')))[0] ?? null;

        if (! $this->node->is('ThrowStatement', 'ThrowExpression') || $created === null) {
            return false;
        }

        foreach ($this->module->ancestorsOf($this->node) as $ancestor) {
            if ($ancestor->isFunction()) {
                return false;
            }

            if ($ancestor->is('CatchClause')) {
                $caught = array_values(array_filter($ancestor->children, static fn (Node $child): bool => $child->is('CatchDeclaration')))[0]?->name ?? null;

                return $caught === null || ! array_any($created->arguments(), static fn (Node $argument): bool => $argument->is('IdentifierName') && $argument->name === $caught);
            }
        }

        return false;
    }

    /**
     * Is this call a constructor telling a collaborator it was handed to act — a method called on one of
     * its parameters, or on a field it filled from one, with the answer thrown away? Keeping an answer, a
     * static guard, the class's own helper and a call tried in a `try` that handles its failure are not.
     */
    public function isConstructorSideEffect(): bool
    {
        $callee = $this->node->is('InvocationExpression') ? $this->node->children[0] : null;

        if ($callee === null || ! $callee->is('SimpleMemberAccessExpression') || ! $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent->is('ExpressionStatement'))) {
            return false;
        }

        $scope = null;

        foreach ($this->module->ancestorsOf($this->node) as $ancestor) {
            if ($ancestor->is('TryStatement') && array_any($ancestor->children, static fn (Node $part): bool => $part->is('CatchClause'))) {
                return false;
            }

            if ($ancestor->isFunction()) {
                $scope = $ancestor;

                break;
            }
        }

        return $scope?->is('ConstructorDeclaration') === true && in_array(self::heldName($callee->children[0]), $this->collaboratorsOf($scope), true);
    }

    /**
     * The names in $constructor that stand for something it was handed: its parameters, and the fields it
     * fills from them.
     *
     * @return list<string>
     */
    private function collaboratorsOf(Node $constructor): array
    {
        $parameters = array_values(array_filter(array_map(
            static fn (Node $parameter): ?string => $parameter->name,
            array_filter(array_merge([], ...array_map(static fn (Node $list): array => $list->children, array_filter($constructor->children, static fn (Node $child): bool => $child->is('ParameterList')))), static fn (Node $node): bool => $node->is('Parameter')),
        )));
        $fields = array_filter(array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $constructor->outermostExpressions())), static fn (Node $assignment): bool => $assignment->is('SimpleAssignmentExpression')
            && $assignment->children[1]->is('IdentifierName')
            && in_array($assignment->children[1]->name, $parameters, true));

        return [...$parameters, ...array_values(array_filter(array_map(static fn (Node $assignment) => self::heldName($assignment->children[0]), $fields)))];
    }

    /**
     * The name $expression reads — `printer` for `printer` and for `this.printer` — or empty.
     */
    private static function heldName(Node $expression): string
    {
        return match (true) {
            $expression->is('IdentifierName') => (string) $expression->name,
            $expression->is('SimpleMemberAccessExpression') && $expression->children[0]->is('ThisExpression') => (string) $expression->children[1]->name,
            default => '',
        };
    }

    /**
     * Is this a write — an assignment, `++` or `--` — to a static field of its own type that is neither
     * `readonly` nor `const`, made from a method or accessor rather than the static constructor or the
     * field's own initializer?
     */
    public function isWritingStaticState(): bool
    {
        $scope = array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->isFunction()))[0] ?? null;

        if (! $this->node->isWrite() || $scope === null || ($scope->is('ConstructorDeclaration') && $scope->hasModifier('static'))) {
            return false;
        }

        $target = $this->node->children[0];

        return $this->enclosingType()->isSomeAnd(static fn (Node $type): bool => in_array(self::fieldWritten($target, $type, $scope), self::mutableStaticFieldsOf($type), true));
    }

    /**
     * The field of $type that $target names — `hits`, or `Counter.hits` through the type's own name —
     * empty for anything else, including a local or parameter of $scope's that shadows it.
     */
    private static function fieldWritten(Node $target, Node $type, Node $scope): string
    {
        if ($target->is('SimpleMemberAccessExpression')) {
            return $target->children[0]->is('IdentifierName') && $target->children[0]->name === $type->name ? (string) $target->children[1]->name : '';
        }

        if (! $target->is('IdentifierName') || in_array($target->name, $scope->ownNames(), true)) {
            return '';
        }

        return (string) $target->name;
    }

    /**
     * The static fields $type declares that anything may overwrite — neither `readonly` nor `const`.
     *
     * @return list<string>
     */
    private static function mutableStaticFieldsOf(Node $type): array
    {
        $fields = array_filter($type->children, static fn (Node $member): bool => $member->is('FieldDeclaration')
            && $member->hasModifier('static')
            && ! $member->hasModifier('readonly')
            && ! $member->hasModifier('const'));

        return array_values(array_filter(array_map(
            static fn (Node $declarator): ?string => $declarator->name,
            array_filter(array_merge([], ...array_map(static fn (Node $field): array => $field->descendants(), $fields)), static fn (Node $node): bool => $node->is('VariableDeclarator')),
        )));
    }

    /**
     * Is this a `?? throw` buried in the work — handed to a call as an argument, or the thing a member is
     * read or called on — rather than assigned or returned as the guard it is?
     */
    public function isBuriedThrow(): bool
    {
        if (! $this->node->is('CoalesceExpression') || ! ($this->node->children[1] ?? null)?->is('ThrowExpression')) {
            return false;
        }

        $inner = $this->node;
        $parent = $this->module->parentOf($inner);

        while ($parent->isSomeAnd(static fn (Node $around): bool => $around->is('ParenthesizedExpression'))) {
            $inner = $parent->unwrap();
            $parent = $this->module->parentOf($inner);
        }

        return $parent->isSomeAnd(static fn (Node $around): bool => $around->is('Argument')
            || ($around->is('SimpleMemberAccessExpression', 'ConditionalAccessExpression') && $around->children[0] === $inner));
    }

    /**
     * Is this the whole of a group test — the outermost `||` of a chain, or an `or` pattern a value is
     * tested against with `is` (never a switch arm's, where the switch is the per-case place)?
     */
    public function isGroupTestRoot(): bool
    {
        $parent = $this->module->parentOf($this->node);

        return match (true) {
            $this->node->is('LogicalOrExpression') => ! $parent->isSomeAnd(static fn (Node $around): bool => $around->is('LogicalOrExpression')),
            $this->node->is('OrPattern') => $parent->isSomeAnd(static fn (Node $around): bool => $around->is('IsPatternExpression')),
            default => false,
        };
    }

    /**
     * Is this a write to the record it sits in — one of its members or instance fields assigned, added to
     * or stepped by a method, an accessor or an operator, after construction? A write in a constructor, an
     * `init` accessor or an initializer (`this with { … }`, `new R { … }`) builds a record rather than
     * changing one, and `??=` only fills a cache nobody has read yet.
     */
    public function isWritingRecordState(): bool
    {
        $scope = array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->isFunction()))[0] ?? null;
        $initializing = $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => str_ends_with($parent->kind, 'InitializerExpression'));

        if (! $this->node->isWrite() || $this->node->is('CoalesceAssignmentExpression') || $initializing || $scope === null || $scope->is('ConstructorDeclaration', 'InitAccessorDeclaration')) {
            return false;
        }

        return $this->enclosingType()->isSomeAnd(fn (Node $type) => $type->isRecord() && $this->node->children[0]->readsMember(array_diff($type->stateNames(), $scope->ownNames())));
    }

    /**
     * Is this a `set` accessor on a property of a record — a door left open to change it after construction?
     */
    public function isRecordSetter(): bool
    {
        return $this->node->is('SetAccessorDeclaration') && $this->enclosingType()->isSomeAnd(static fn (Node $type) => $type->isRecord());
    }

    /**
     * Is this the instance a type keeps of itself as its Null Object — the initial value of one of its own
     * `static` fields or properties, `public static Profile None { get; } = new() { … }`?
     */
    public function isNullObjectOfItsType(): bool
    {
        $member = array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->role === 'member'))[0] ?? null;

        return $member?->is('FieldDeclaration', 'PropertyDeclaration') === true
            && $member->hasModifier('static')
            && $this->enclosingType()->isSomeAnd(fn (Node $type) => $type->symbol === $this->node->type?->name);
    }

    /**
     * Is this the whole of a `&&` chain — not one side of a larger one, parentheses aside?
     */
    public function isOutermostAnd(): bool
    {
        return $this->node->is('LogicalAndExpression')
            && ! $this->module->parentOf($this->outermostParentheses())->isSomeAnd(static fn (Node $around): bool => $around->is('LogicalAndExpression'));
    }

    /**
     * Is this expression the value a variable is given or assigned — stored rather than asked?
     */
    public function isStoredValue(): bool
    {
        return $this->module->parentOf($this->outermostParentheses())->isSomeAnd(static fn (Node $around): bool => $around->is('EqualsValueClause', 'SimpleAssignmentExpression'));
    }

    /**
     * Is this the first statement of a run of $lines or more `AppendLine` statements on one builder, $fixed of
     * them writing text written into the source — a template taken apart and written one line at a time?
     */
    public function startsAppendLineRun(int $lines, int $fixed): bool
    {
        if (! $this->node->isAppendLine()) {
            return false;
        }

        return $this->module->parentOf($this->node)->isSomeAnd(function (Node $block) use ($lines, $fixed): bool {
            $at = (int) array_search($this->node, $block->children, true);
            $receiver = $this->node->appendReceiver();
            $onSame = static fn (?Node $statement): bool => $statement?->isAppendLine() === true && $statement->appendReceiver() === $receiver;

            if ($onSame($block->children[$at - 1] ?? null)) {
                return false;
            }

            $run = [];

            for ($i = $at; $onSame($block->children[$i] ?? null); $i++) {
                $run[] = $block->children[$i];
            }

            return count($run) >= $lines && count(array_filter($run, static fn (Node $statement): bool => $statement->isAppendingFixedText())) >= $fixed;
        });
    }

    /**
     * Is this expression what its member hands back — the value of a `return`, or of an expression body?
     */
    public function isReturned(): bool
    {
        return $this->module->parentOf($this->outermostParentheses())->isSomeAnd(static fn (Node $around): bool => $around->is('ReturnStatement', 'ArrowExpressionClause'));
    }

    /**
     * This expression as its surroundings see it — wrapped in every pair of parentheses around it.
     */
    private function outermostParentheses(): Node
    {
        $inner = $this->node;

        while ($this->module->parentOf($inner)->isSomeAnd(static fn (Node $around): bool => $around->is('ParenthesizedExpression'))) {
            $inner = $this->module->parentOf($inner)->unwrap();
        }

        return $inner;
    }

    /**
     * Is this expression a branch of a conditional expression — so a chain of them is reported once, at its
     * outermost?
     */
    public function isConditionalBranch(): bool
    {
        $inner = $this->outermostParentheses();

        return $this->module->parentOf($inner)->isSomeAnd(static fn (Node $around): bool => $around->is('ConditionalExpression') && $around->children[0] !== $inner);
    }

    /**
     * Does this member answer a lookup miss with an invented empty value? Every value it returns — each
     * arm of a conditional counted on its own — is either `""`/`0`/`false`, or what a dictionary lookup
     * found: the lookup itself, or the `out` variable a `TryGetValue` in this member filled.
     */
    public function isInventingOnMiss(): bool
    {
        $body = $this->node->functionBody();

        if ($body->isNone()) {
            return false;
        }

        $scope = [$body->unwrap(), ...$body->unwrap()->descendants()];
        $answers = array_merge([], ...array_map(
            static fn (Node $return): array => $return->returnedValue()->mapOr([], static fn (Node $value): array => $value->answers()),
            array_values(array_filter($scope, static fn (Node $node): bool => $node->isReturn())),
        ));
        $found = self::lookedUpNames($scope);
        $invented = array_filter($answers, static fn (Node $answer): bool => $answer->isEmptyScalar());
        $real = array_filter($answers, static fn (Node $answer): bool => ! $answer->isEmptyScalar());

        return $invented !== [] && $real !== [] && array_all($real, static fn (Node $answer): bool => $answer->isLookup() || ($answer->is('IdentifierName') && in_array($answer->name, $found, true)));
    }

    /**
     * The names of the `out` variables a `TryGetValue` among $scope fills.
     *
     * @param  list<Node>  $scope
     * @return list<string>
     */
    private static function lookedUpNames(array $scope): array
    {
        $names = [];

        foreach ($scope as $node) {
            foreach ($node->expressions() as $expression) {
                foreach (array_filter($expression->flatten(), static fn (Node $call): bool => $call->isLookup() && $call->isCall()) as $lookup) {
                    foreach ($lookup->flatten() as $part) {
                        if ($part->is('DeclarationExpression')) {
                            $names = [...$names, ...array_filter(array_map(static fn (Node $designation): ?string => $designation->name, array_filter($part->children, static fn (Node $child): bool => $child->is('SingleVariableDesignation'))))];
                        }
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Is this written where a type builds itself from loose data — a constructor, or a static factory
     * whose declared return type is the type it sits in? Reading the input by name there is the edge.
     */
    public function isWithinNamedConstructor(): bool
    {
        $member = array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->is('ConstructorDeclaration', 'MethodDeclaration')))[0] ?? null;

        return $member !== null && $this->isNamedConstructorOf($member);
    }

    /**
     * Is this member a constructor, or a static factory whose declared return type is the type it sits in?
     */
    public function isNamedConstructor(): bool
    {
        return $this->isNamedConstructorOf($this->node);
    }

    /**
     * Is this blank-defaulted parameter or property asked, in its own scope, whether it is blank — the
     * question that proves the blank stands in for absence? A parameter is asked in its function, a
     * property in its type.
     */
    public function defaultedNameTestedForBlankness(): bool
    {
        $name = (string) $this->node->name;
        $scope = $this->node->is('Parameter')
            ? Option::fromNullable(array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->isFunction()))[0] ?? null)
            : $this->enclosingType();

        return $scope->isSomeAnd(static fn (Node $owner): bool => array_any(
            array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $owner->outermostExpressions())),
            static fn (Node $expression): bool => $expression->testsBlanknessOf($name),
        ));
    }

    /**
     * What this node belongs to — the type it is declared in, or its file for a top-level function —
     * named by where it is, so two of them compare.
     */
    public function owner(): string
    {
        return $this->enclosingType()->mapOr($this->module->file, fn (Node $type): string => "{$this->module->file}::{$type->name}");
    }

    private function isNamedConstructorOf(Node $member): bool
    {
        $returns = array_values(array_filter($member->children(), static fn (Node $child): bool => $child->role === 'type'))[0] ?? null;

        return $member->is('ConstructorDeclaration')
            || ($member->hasModifier('static') && $this->enclosingType()->isSomeAnd(static fn (Node $type): bool => $returns?->name === $type->name));
    }

    /**
     * Does this sit in a method that reports failure through its answer — one that answers `bool` and hands
     * its results back through an `out` parameter, the `TryParse` shape, where `false` IS the failure?
     */
    public function isWithinTryMethod(): bool
    {
        $method = array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->is('MethodDeclaration', 'LocalFunctionStatement')))[0] ?? null;
        $parameters = array_filter($method?->children ?? [], static fn (Node $child): bool => $child->is('ParameterList'));

        return array_any(array_merge([], ...array_map(static fn (Node $list): array => $list->children, $parameters)), static fn (Node $parameter): bool => $parameter->hasModifier('out'));
    }

    /**
     * Is this a state member — a field, a constant, a stored property — declared below one of its type's
     * constructors or methods? An interface declares a contract, not state, so its members have no place to be.
     */
    public function isMemberAfterMethod(): bool
    {
        if (! $this->node->isStateMember()) {
            return false;
        }

        return $this->module->parentOf($this->node)->isSomeAnd(function (Node $type): bool {
            if ($type->is('InterfaceDeclaration')) {
                return false;
            }

            $before = array_slice($type->children, 0, (int) array_search($this->node, $type->children, true));

            return array_any($before, static fn (Node $member): bool => $member->is('MethodDeclaration', 'ConstructorDeclaration'));
        });
    }

    /**
     * Is this a constant declared below one of its type's instance fields or stored properties — the top of the
     * type read out of order?
     */
    public function isMemberOutOfOrder(): bool
    {
        if (! $this->node->isConstantMember()) {
            return false;
        }

        return $this->module->parentOf($this->node)->isSomeAnd(function (Node $type): bool {
            $before = array_slice($type->children, 0, (int) array_search($this->node, $type->children, true));

            return array_any($before, static fn (Node $member): bool => $member->isInstanceStateMember());
        });
    }

    /**
     * @return Option<Node>
     */
    private function enclosingType(): Option
    {
        return Option::fromNullable(array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->is('ClassDeclaration', 'RecordDeclaration', 'StructDeclaration', 'RecordStructDeclaration', 'InterfaceDeclaration')))[0] ?? null);
    }

    /**
     * Does this keyed read read a dictionary the code in hand owns — a parameter or a local of a function
     * it sits in — rather than one reached through another object?
     */
    public function isReadingOwnDictionary(): bool
    {
        $names = array_merge([], ...array_map(static fn (Node $function): array => $function->ownNames(), array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->isFunction())));

        return $this->node->readsDictionaryNamed($names);
    }

    /**
     * Is this indexer being assigned to — a write, not a read?
     */
    public function isAssignedTo(): bool
    {
        return $this->module->parentOf($this->node)->isSomeAnd(fn (Node $parent): bool => str_ends_with($parent->kind, 'AssignmentExpression') && $parent->children[0] === $this->node);
    }

    /**
     * Does this sit in a member that overrides or implements a contract — whose signature, and whatever
     * data it is handed, the contract decided?
     */
    public function isWithinOverride(): bool
    {
        return array_any($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node->inherited);
    }

    /**
     * Does this feed straight into an object being created — an argument or an initializer of a `new`
     * within the same function? Reading loose data there is converting it, at the edge.
     */
    public function isBuildingAnObject(): bool
    {
        foreach ($this->module->ancestorsOf($this->node) as $ancestor) {
            if ($ancestor->isFunction()) {
                return false;
            }

            if ($ancestor->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The string literals this dispatches a value on — a `switch` statement's case labels, a `switch`
     * expression's arms, or the rungs of an `if` ladder — and none for anything else.
     *
     * @return list<string>
     */
    public function comparedLiterals(): array
    {
        $compared = match (true) {
            $this->node->is('SwitchStatement', 'SwitchExpression') => array_filter($this->node->descendants(), static fn (Node $node): bool => $node->is('CaseSwitchLabel', 'ConstantPattern')),
            $this->node->is('IfStatement') && ! $this->isElseIf() => array_map(static fn (Node $rung): Node => $rung->expressions()[0], self::rungs($this->node)),
            default => [],
        };
        $values = array_merge([], ...array_map(static fn (Node $node): array => $node->is('CaseSwitchLabel', 'ConstantPattern') ? $node->expressions() : [$node], $compared));
        $literals = array_merge([], ...array_map(static fn (Node $value): array => $value->flatten(), $values));

        return array_values(array_map(static fn (Node $literal): string => (string) $literal->text, array_filter($literals, static fn (Node $node): bool => $node->is('StringLiteralExpression'))));
    }

    /**
     * How many choices this node sits inside, within the function it belongs to: each `if`, loop or
     * `switch` whose body holds it — an `else if` a rung of the ladder it continues, not a level of its
     * own.
     */
    public function branchingDepth(): int
    {
        $depth = 0;
        $child = $this->node;

        foreach ($this->module->ancestorsOf($this->node) as $parent) {
            if ($parent->isFunction()) {
                break;
            }

            if ($parent->isBranchingConstruct() && ! self::continuesTheLadder($child)) {
                $depth++;
            }

            $child = $parent;
        }

        return $depth;
    }

    /**
     * Is $child the `else` of an `if` that holds only the next `if` — a rung, not a body?
     */
    private static function continuesTheLadder(Node $child): bool
    {
        return $child->is('ElseClause') && ($child->children()[0] ?? null)?->is('IfStatement') === true;
    }

    protected static function syntaxHash(): string
    {
        return StructuralHash::class;
    }

    /**
     * The run of line and block comments standing directly above this statement, fixture markers set aside.
     *
     * @return list<Comment>
     */
    public function commentsAbove(): array
    {
        return array_values(array_filter(
            $this->module->commentsAbove($this->node),
            static fn (Comment $comment): bool => $comment->kind !== CommentKind::Documentation && ! DeclarationMarkers::isMarkerComment($comment->prose()),
        ));
    }

    /**
     * The content words the comments above this statement say, stemmed — none when one of them holds code.
     *
     * @return list<string>
     */
    public function commentWords(): array
    {
        $comments = $this->commentsAbove();

        if (array_any($comments, static fn (Comment $comment): bool => $comment->code)) {
            return [];
        }

        return array_values(array_unique(Prose::words(implode(' ', array_map(static fn (Comment $comment): string => $comment->prose(), $comments)))));
    }

    /**
     * The words this statement's own head spells, stemmed — {@see CodeWords}.
     *
     * @return list<string>
     */
    public function codeWords(): array
    {
        return CodeWords::of($this->node);
    }

    /**
     * Does this method look its target up in a container parameter by a key parameter — `Rename(Workflow workflow,
     * string nodeId)` doing `var node = workflow.Graph.Node(nodeId)` — keep what it found in a local, and use the
     * container for nothing but reading its properties, while being more than the resolver? Only a container the
     * codebase declares counts: a library's type (an `Assembly`, a `Regex`) answers its own questions.
     */
    public function unpacksTargetFromContainerParam(Codebase $codebase): bool
    {
        $body = $this->node->functionBody();

        if (! $this->node->is('MethodDeclaration') || $body->isNone()) {
            return false;
        }

        $parameters = $this->node->parameters();
        $owners = array_map(static fn (Node $parameter): string => (string) $parameter->name, array_filter($parameters, static fn (Node $parameter): bool => $parameter->type !== null && ! $parameter->type->isValueType && $codebase->declaresType(rtrim($parameter->type->name, '?'))));
        $keys = array_map(static fn (Node $parameter): string => (string) $parameter->name, array_filter($parameters, static fn (Node $parameter): bool => in_array(rtrim((string) $parameter->type?->name, '?'), self::KEY_TYPES, true)));
        $reads = array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $body->unwrap()->outermostExpressions()));

        return $owners !== [] && $keys !== [] && array_any($reads, fn (Node $lookup) => $this->unpacksFrom($lookup, $owners, $keys, $reads, $body->unwrap()));
    }

    /**
     * Is $lookup the unpacking — keyed by one of $keys on one of $owners, kept in a local that is more than the
     * resolved value handed back, the owner otherwise only read through?
     *
     * @param  list<string>  $owners
     * @param  list<string>  $keys
     * @param  list<Node>  $reads
     */
    private function unpacksFrom(Node $lookup, array $owners, array $keys, array $reads, Node $body): bool
    {
        $root = $lookup->lookupRootKeyedBy($keys);
        $local = $root === null ? null : $this->module->capturedLocal($lookup);

        return $root !== null
            && in_array($root->name, $owners, true)
            && $local !== null
            && ! $body->isResolverReturning($local)
            && $this->module->isOnlyReadThrough((string) $root->name, $reads);
    }

    /**
     * The parameter this method envies — the one other object it loops the collection of or writes the members
     * of, reaching into it more than into its own state — none when it envies nothing. A type that may fill a
     * contract is a polymorphic component meant to act on other types, a method that builds anything is a
     * mapper, a parameter of the method's own type is its own business, and a loop handing each element to one
     * of the method's own collaborators is orchestration.
     *
     * @return Option<string>
     */
    public function enviedParameter(Codebase $codebase): Option
    {
        $host = $this->enclosingType();

        if ($host->isNoneOr(static fn (Node $type): bool => $type->mayFillAContract()) || $this->constructs()) {
            return Option::none();
        }

        $own = $host->unwrap()->stateNames();
        $hostType = (string) $host->unwrap()->symbol;
        $owned = array_map(static fn (Node $parameter): string => (string) $parameter->name, array_filter($this->node->parameters(), static fn (Node $parameter): bool => $parameter->type !== null
            && ! $parameter->type->isValueType
            && rtrim($parameter->type->name, '?') !== $hostType
            && $codebase->declaresType(rtrim($parameter->type->name, '?'))));
        $reaches = $this->node->memberReachesOn($owned);
        $ownReaches = array_sum(array_map(static fn (Node $expression): int => count($expression->ownStateReferences($own)), $this->node->outermostExpressions()));

        if (count($reaches) !== 1 || reset($reaches) <= $ownReaches) {
            return Option::none(); // two envied objects is orchestration; no more reaches than its own is not envy
        }

        $envied = (string) array_key_first($reaches);
        $loops = $this->node->loopsOver($envied);
        $mutates = $this->node->writesMemberOf($envied);
        $iterates = $loops !== [] || $this->node->queriesCollectionOf($envied);

        if ($iterates && ! $mutates && $loops !== [] && array_all($loops, fn (Node $loop) => $this->handsElementOn($loop, $own))) {
            return Option::none();
        }

        return $iterates || $mutates ? Option::some($envied) : Option::none();
    }

    /**
     * Does this build anything — `new` of any type, named or anonymous? Construction is a mapper's or a factory's
     * work, whose job is to read another object; the PHP twin reads it the same way.
     */
    private function constructs(): bool
    {
        $parts = array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $this->node->outermostExpressions()));

        return array_any($parts, static fn (Node $node): bool => $node->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression', 'AnonymousObjectCreationExpression'));
    }

    /**
     * Does $loop hand its element to one of the collaborators this type holds under $own — `printer.Print(line)` —
     * the orchestrator doing its own job? Calling this method again is recursion, which belongs with the collection.
     *
     * @param  list<string>  $own
     */
    private function handsElementOn(Node $loop, array $own): bool
    {
        $calls = array_filter(array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $loop->outermostExpressions())), static fn (Node $node): bool => $node->isCall());

        return array_any($calls, fn (Node $call): bool => $call->children[0]->is('SimpleMemberAccessExpression')
            && in_array($call->children[0]->children[0]->memberName(), $own, true)
            && $call->calledName() !== $this->node->name
            && array_any($call->arguments(), static fn (Node $argument): bool => $argument->is('IdentifierName') && $argument->name === $loop->name));
    }
}
