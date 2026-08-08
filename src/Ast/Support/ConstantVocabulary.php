<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

/**
 * Which ARGUMENT SLOTS — a method plus an argument position — a codebase spells with a named
 * constant, and what vocabulary each draws on. Indexing slots rather than values is what makes a
 * magic string decidable: a literal matching some constant proves nothing, but a slot the project
 * already spells `expect(Token::COLON)` and once spells `expect('{')` is the codebase contradicting
 * itself.
 */
final class ConstantVocabulary
{
    use MemoisedPerCodebase;

    /**
     * @param  array<string, list<string>>  $vocabularyBySlot  slot key => the constant-holding
     *         classes seen filling it
     * @param  array<string, array<string, string>>  $namesByClass  class FQCN => (value => constant name)
     */
    private function __construct(
        private readonly array $vocabularyBySlot,
        private readonly array $namesByClass,
    ) {}

    protected static function build(Codebase $codebase): static
    {
        $namesByClass = self::constantsByClass($codebase);

        return new self(self::slotsSpelledWithConstants($codebase, $namesByClass), $namesByClass);
    }

    /**
     * The constant that ALREADY NAMES this literal in the slot it fills — `Token::BRACE_OPEN` for a
     * `'{'` written where the codebase elsewhere passes `Token::…`. Null when there is no such
     * evidence, which is the answer for almost every string in a codebase.
     */
    public function nameFor(AstNode $literal): ?string
    {
        if (! $literal->node instanceof String_) {
            return null;
        }

        $slot = self::slotOf($literal->node);

        if ($slot === null) {
            return null;
        }

        foreach ($this->vocabularyBySlot[$slot] ?? [] as $class) {
            $name = $this->namesByClass[$class][$literal->node->value] ?? null;

            if ($name !== null) {
                return self::shortName($class) . '::' . $name;
            }
        }

        return null;
    }

    /**
     * Every slot filled by a class constant somewhere, mapped to the classes those constants belong
     * to. A slot spelled from two vocabularies keeps both.
     *
     * @param  array<string, array<string, string>>  $namesByClass
     * @return array<string, list<string>>
     */
    private static function slotsSpelledWithConstants(Codebase $codebase, array $namesByClass): array
    {
        $slots = [];
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassConstFetch::class) as $fetch) {
                /**
                 * @var ClassConstFetch $fetch
                 */
                $class = $fetch->class instanceof Node\Name ? $fetch->class->toString() : null;
                $slot = self::slotOf($fetch);

                if ($class === null || $slot === null || ! isset($namesByClass[$class])) {
                    continue;
                }

                if (! in_array($class, $slots[$slot] ?? [], true)) {
                    $slots[$slot][] = $class;
                }
            }
        }

        return $slots;
    }

    /**
     * Every public class constant holding a non-trivial string, as `class => (value => name)` — the
     * lookup that turns a literal back into the name it should have been written under.
     *
     * A NUMBER-like or EMPTY value is dropped: `'0'` and `''` belong to every index test and string
     * default in a codebase, so a constant holding one would claim them all. Only PUBLIC constants
     * count — a `private const` is one class's own shorthand, not vocabulary another file was meant
     * to reach for.
     *
     * @return array<string, array<string, string>>
     */
    private static function constantsByClass(Codebase $codebase): array
    {
        $byClass = [];

        foreach ($codebase->files() as $file) {
            foreach (new NodeFinder()->findInstanceOf($file->ast, Node\Stmt\ClassLike::class) as $declaration) {
                /**
                 * @var Node\Stmt\ClassLike $declaration
                 */
                $owner = ($declaration->namespacedName ?? null)?->toString();

                if ($owner === null) {
                    continue;
                }

                foreach ($declaration->stmts as $statement) {
                    if (! $statement instanceof Node\Stmt\ClassConst || $statement->isPrivate() || $statement->isProtected()) {
                        continue;
                    }

                    foreach ($statement->consts as $const) {
                        if ($const->value instanceof String_ && $const->value->value !== '' && ! is_numeric($const->value->value)) {
                            $byClass[$owner][$const->value->value] = $const->name->toString();
                        }
                    }
                }
            }
        }

        return $byClass;
    }

    /**
     * The SLOT this expression fills — `Parser::expect#0` — or null when it is not a direct call
     * argument, or when the receiver's type cannot be named with certainty.
     *
     * An unresolvable receiver yields NO slot rather than a guessed one: two unrelated classes both
     * having a `write()` must never share a slot, because that is how a rule starts flagging code
     * that never disagreed with anything.
     */
    private static function slotOf(Node $expression): ?string
    {
        $argument = $expression->getAttribute('parent');

        if (! $argument instanceof Arg) {
            return null;
        }

        $call = $argument->getAttribute('parent');

        if (! $call instanceof MethodCall && ! $call instanceof StaticCall) {
            return null;
        }

        $target = self::targetOf($call);

        if ($target === null) {
            return null;
        }

        $index = self::indexOf($call, $argument);

        return $index === null ? null : $target . '#' . $index;
    }

    /**
     * The METHOD a call names, qualified enough that no two collide — or null when it cannot be
     * resolved without guessing. Only a method's parameter is a decision the project made: a global
     * function's is shared by every caller, so one file splitting a type union on
     * `UnionType::SEPARATOR` says nothing about another splitting a cache key on `'|'`.
     */
    private static function targetOf(MethodCall|StaticCall $call): ?string
    {
        if ($call instanceof MethodCall) {
            return self::thisCallTarget($call);
        }

        if ($call instanceof StaticCall) {
            $class = $call->class instanceof Node\Name ? $call->class->toString() : null;

            return $class !== null && $call->name instanceof Node\Identifier
                ? self::resolveSelf($class, $call) . '::' . $call->name->toString()
                : null;
        }

        return null;
    }

    /**
     * A `$this->method(…)` call, keyed by the class it is written in — the one receiver whose type
     * is certain without resolving anything. Any other variable receiver is left unkeyed.
     */
    private static function thisCallTarget(MethodCall $call): ?string
    {
        $node = new AstNode($call);

        if (! $node->isThisCall()) {
            return null;
        }

        $owner = $node->enclosingClassName();

        return $owner !== null && $call->name instanceof Node\Identifier
            ? $owner . '::' . $call->name->toString()
            : null;
    }

    /**
     * `self`/`static` named from inside a class is that class — resolved so a `self::of(…)` and a
     * `Thing::of(…)` in another file land in the SAME slot rather than two.
     */
    private static function resolveSelf(string $class, Node $at): string
    {
        return in_array($class, ['self', 'static'], true)
            ? new AstNode($at)->enclosingClassName() ?? $class
            : $class;
    }

    private static function indexOf(MethodCall|StaticCall $call, Arg $argument): ?int
    {
        foreach ($call->args as $index => $candidate) {
            if ($candidate === $argument) {
                return $index;
            }
        }

        return null;
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return $parts[count($parts) - 1];
    }
}
