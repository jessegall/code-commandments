<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\MethodBodyHash;
use JesseGall\CodeCommandments\Support\Environment;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a method whose body is duplicated elsewhere in the codebase — the same
 * structure (modulo variable names) repeated, which should be extracted.
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static minLines(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.64.0')]
class DuplicateCodeProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_MIN_LINES = 4;

    /** Built-in TYPE predicates whose negated guard is the "parse a maybe-X" preamble (#202). */
    private const TYPE_PREDICATES = ['is_array', 'is_string', 'is_int', 'is_integer', 'is_bool', 'is_float', 'is_numeric', 'is_object', 'is_iterable', 'is_null', 'is_callable', 'is_scalar'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Extract duplicated code fragments instead of copy-pasting a method body';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A method body is structurally identical (same statements, modulo '
                . 'variable names) to another method of >= min_lines somewhere in '
                . 'the codebase — a copy-paste that will drift: a fix to one copy '
                . 'silently leaves the others wrong.'
            )
            ->leaveWhen(
                'The two bodies only coincidentally look alike (boilerplate the '
                . 'framework dictates, a trivial accessor) and sharing them would '
                . 'couple things that should stay independent.'
            )
            ->whenUnsure(
                'If the duplicated body is real logic with a name, extract it — a '
                . 'private method, a trait, or a small collaborator both call. If '
                . 'it is incidental shape, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Duplicated code is a maintenance trap: the same logic copy-pasted across
methods drifts apart — a bug fixed in one copy stays broken in the others,
and a behaviour change must be made N times.

This prophet fingerprints every method body across the scroll (pretty-printed
with local variable names canonicalised, so a copy that merely renamed its
variables still matches) and flags a method whose body appears more than once
and is at least `min_lines` printed lines long.

    // src/A.php
    private function expandRoots(array $roots): array { /* 18 lines */ }

    // src/B.php
    private function expandRoots(array $roots): array { /* the same 18 lines */ }
    //          ^ Duplicated code fragment (18 lines) — extract it.

It also catches a duplicated PREAMBLE: when two methods share a leading run of
statements (>= `min_lines` printed lines) and then DIVERGE, their whole-body
fingerprints differ but the shared prefix is still flagged ("Duplicated
preamble") — extract the common opening into a helper both call.

    public function resolve($r): array {
        $node = $this->nodeById($r->id);          // ┐ identical leading run in
        if ($node->isEmpty()) { return []; }       // │ resolve() of two classes,
        $out = $this->findOutput($node->get());    // ┘ then each diverges below.
        // …builds a candidate list…  /  …builds a per-port verdict map…
    }

The fix is not auto-fixable — WHERE the shared logic should live (a private
method, a trait, a collaborator) is a design decision. Extract it to one home
and call that from both sites.

WHAT FIRES — a method body (or a shared leading run) of >= `min_lines` printed
lines (default 4) whose canonical fingerprint matches at least one OTHER method
in the scroll.

WHAT DOES NOT — bodies/runs shorter than `min_lines`, and (because it is a
cross-file rule) anything when no codebase index could be built. The default
floor is 4 lines: an exact, byte-identical helper duplicated across files is
worth extracting even when small — raise `min_lines` if your codebase wants a
higher bar.

Configuration:

    Backend\DuplicateCodeProphet::class => [
        'min_lines' => 4,   // smallest duplicated body / preamble worth extracting
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Cross-file rule: without the index there is nothing to compare against.
        if ($this->index === null) {
            return $this->righteous();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $minLines = $this->minLines();
        $self = realpath($filePath) ?: $filePath;
        $finder = new NodeFinder;
        $warnings = [];

        /** @var array<Node\Stmt\ClassMethod> $methods */
        $methods = $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            $line = $method->getStartLine();
            $body = MethodBodyHash::of($method, $minLines);

            if ($body !== null) {
                $others = $this->otherOccurrences($this->index->methodBodyOccurrences($body['hash']), $self, $line);

                if ($others !== []) {
                    $warnings[] = $this->warningAt(
                        $line,
                        $this->messageFor($method->name->toString(), $body['lines'], $others),
                        null,
                        'duplicate:' . $body['hash'],
                    );

                    // The whole body is already flagged — don't also report its
                    // prefixes as fragment duplicates.
                    continue;
                }
            }

            // Whole body is unique — but a LEADING run may still be a preamble
            // shared with another method that then diverges. Report the longest.
            $fragment = $this->longestSharedFragment($method, $minLines, $self, $line);

            // #202: a shared LEADING run that is just the ubiquitous "parse a maybe-
            // array" scaffolding — a type-guard clause, an accumulator init, then a
            // loop header — is not duplicated LOGIC; the bodies diverge inside the
            // loop. Extracting it would force a generic higher-order helper that
            // obscures a 3-line idiom. (A truly duplicated whole body still fires via
            // the body-hash path above.)
            if ($fragment !== null && $this->opensWithGenericGuardPreamble($method)) {
                continue;
            }

            if ($fragment !== null) {
                $warnings[] = $this->warningAt(
                    $line,
                    $this->fragmentMessageFor($method->name->toString(), $fragment['lines'], $fragment['others']),
                    null,
                    'duplicate-prefix:' . $fragment['hash'],
                );
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @param  list<array{file: string, class: string, method: string, line: int, lines: int}>  $others
     */
    private function messageFor(string $method, int $lines, array $others): string
    {
        $first = $others[0];
        $where = sprintf('%s::%s() (%s:%d)', $this->shortName($first['class']), $first['method'], $this->relative($first['file']), $first['line']);
        $more = count($others) - 1;

        return sprintf(
            'Duplicated code fragment (%d lines): %s() is structurally identical to %s%s. Extract the shared logic into one home — a private method, a trait, or a collaborator — and call it from each site.',
            $lines,
            $method,
            $where,
            $more > 0 ? sprintf(' (+%d more)', $more) : '',
        );
    }

    /**
     * The longest LEADING fragment of $method that is shared with at least one
     * OTHER method, or null. Walks the method's prefix fingerprints from longest
     * to shortest so the reported run is maximal.
     *
     * @return array{hash: string, lines: int, others: list<array{file: string, class: string, method: string, line: int, lines: int}>}|null
     */
    /**
     * Whether the method opens with a generic guard preamble that leads straight
     * into a loop — `if (! is_array($x)) return …;  $acc = …;  foreach (…)` — where
     * the real, diverging work happens INSIDE the loop. Such a shared leading run is
     * idiom, not duplicated logic (#202). Only scaffolding statements (a guard clause
     * that exits, and simple initialiser assignments) may precede the loop.
     */
    private function opensWithGenericGuardPreamble(Node\Stmt\ClassMethod $method): bool
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof Node\Stmt\Foreach_
                || $stmt instanceof Node\Stmt\For_
                || $stmt instanceof Node\Stmt\While_
            ) {
                return true; // reached the loop with only trivial scaffolding before it
            }

            // Only a TYPE-PREDICATE guard (`if (! is_array($v)) return …`) and an
            // EMPTY-COLLECTION accumulator init (`$acc = []` / `T_Array::empty()`) may
            // precede the loop. A domain-method guard (`if ($node->isEmpty())`) or an
            // assignment from a real call (`$d = $node->getOrThrow()`) is substantive
            // logic — a genuine shared preamble worth extracting — so bail out.
            if ($stmt instanceof Node\Stmt\If_ && $this->isTypePredicateGuard($stmt)) {
                continue;
            }

            if ($stmt instanceof Node\Stmt\Expression
                && $stmt->expr instanceof Node\Expr\Assign
                && $this->isEmptyCollectionInit($stmt->expr->expr)
            ) {
                continue;
            }

            return false;
        }

        return false;
    }

    /**
     * A guard clause whose condition tests a built-in TYPE predicate (`is_array`,
     * `is_string`, …) — the "is this the shape I expect?" gate, not a domain check.
     */
    private function isTypePredicateGuard(Node\Stmt\If_ $if): bool
    {
        if (! $this->isGuardClause($if)) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf([$if->cond], Node\Expr\FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && in_array(strtolower($call->name->getLast()), self::TYPE_PREDICATES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An empty-collection initialiser — `[]`, a literal array, `T_Array::empty()`, or
     * a `T_Array::EMPTY`-style constant: an accumulator, not a value from real work.
     */
    private function isEmptyCollectionInit(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\Array_
            || ($expr instanceof Node\Expr\StaticCall && $expr->name instanceof Node\Identifier && strtolower($expr->name->toString()) === 'empty')
            || ($expr instanceof Node\Expr\ClassConstFetch && $expr->name instanceof Node\Identifier && strtoupper($expr->name->toString()) === 'EMPTY');
    }

    /**
     * A guard clause: an `if` with no else/elseif whose body only exits (return or
     * throw) — `if (! is_array($v)) return T_Array::empty();`.
     */
    private function isGuardClause(Node\Stmt\If_ $if): bool
    {
        if ($if->elseifs !== [] || $if->else !== null || $if->stmts === []) {
            return false;
        }

        foreach ($if->stmts as $stmt) {
            $isExit = $stmt instanceof Node\Stmt\Return_
                || $stmt instanceof Node\Stmt\Throw_
                || ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Node\Expr\Throw_);

            if (! $isExit) {
                return false;
            }
        }

        return true;
    }

    private function longestSharedFragment(Node\Stmt\ClassMethod $method, int $minLines, string $self, int $line): ?array
    {
        $fragments = MethodBodyHash::leadingFragments($method, $minLines);

        // leadingFragments yields shortest → longest; walk it in reverse.
        foreach (array_reverse($fragments) as $fragment) {
            $others = $this->otherOccurrences($this->index->methodFragmentOccurrences($fragment['hash']), $self, $line);

            if ($others !== []) {
                return ['hash' => $fragment['hash'], 'lines' => $fragment['lines'], 'others' => $others];
            }
        }

        return null;
    }

    /**
     * Occurrences with the method at ($self, $line) — the one being judged —
     * filtered out, so a method never reads as a duplicate of itself.
     *
     * @param  list<array{file: string, class: string, method: string, line: int, lines: int}>  $occurrences
     * @return list<array{file: string, class: string, method: string, line: int, lines: int}>
     */
    private function otherOccurrences(array $occurrences, string $self, int $line): array
    {
        return array_values(array_filter(
            $occurrences,
            fn (array $occ): bool => ! ((realpath($occ['file']) ?: $occ['file']) === $self && $occ['line'] === $line),
        ));
    }

    /**
     * @param  list<array{file: string, class: string, method: string, line: int, lines: int}>  $others
     */
    private function fragmentMessageFor(string $method, int $lines, array $others): string
    {
        $first = $others[0];
        $where = sprintf('%s::%s() (%s:%d)', $this->shortName($first['class']), $first['method'], $this->relative($first['file']), $first['line']);
        $more = count($others) - 1;

        return sprintf(
            'Duplicated preamble (%d lines): the opening of %s() is structurally identical to the start of %s%s — the methods then diverge. Extract the shared leading sequence into one home (a private helper or collaborator) and call it from each.',
            $lines,
            $method,
            $where,
            $more > 0 ? sprintf(' (+%d more)', $more) : '',
        );
    }

    private function minLines(): int
    {
        $value = $this->config('min_lines', self::DEFAULT_MIN_LINES);

        return is_numeric($value) ? max(3, (int) $value) : self::DEFAULT_MIN_LINES;
    }

    private function relative(string $file): string
    {
        $base = rtrim(Environment::basePath(), '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
