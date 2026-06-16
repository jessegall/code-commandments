<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\EnumCaseGroup;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a recognisable subset of an enum's cases inlined as an array literal
 * when that same group is duplicated elsewhere in the codebase — it should
 * live as a named accessor on the enum, not be re-inlined.
 */
#[IntroducedIn('1.54.0')]
class PreferEnumCaseGroupsProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_MIN_GROUP = 3;

    private const DEFAULT_MIN_REUSE = 2;

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function description(): string
    {
        return 'Name reused subsets of an enum on the enum — do not re-inline the same case-group';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'The same subset of an enum\'s cases is inlined as an array in two '
                . 'or more places and the group has a clear name (numeric, textual, '
                . 'terminal, editable…). Each inline copy is a named concept in '
                . 'disguise; a drift in one copy silently diverges from the others.'
            )
            ->leaveWhen(
                'The array is a genuine one-off, or the grouping carries no '
                . 'meaningful name — an arbitrary ad-hoc selection that would never '
                . 'read well as a method on the enum.'
            )
            ->whenUnsure(
                'Leave it. Only promote a group to a named accessor when you can '
                . 'give it an honest name and it is actually reused.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When a recognisable SUBSET of an enum's cases is inlined as an array
literal — and that same group is duplicated elsewhere — it is a named
concept in disguise. Give it a name ON the enum and call that, instead
of re-inlining the subset everywhere it is needed.

Bad — the same two groups, inlined and duplicated across the codebase:

    $operators = $numeric
        ? [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::GreaterThan,
           CompareOperator::GreaterOrEqual, CompareOperator::LessThan, CompareOperator::LessOrEqual]
        : [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::StartsWith,
           CompareOperator::Contains, CompareOperator::EndsWith];

Good — each group is named once, on the enum:

    enum CompareOperator
    {
        // …cases…

        /** @return list<self> */
        public static function numeric(): array
        {
            return [self::Equals, self::NotEquals, self::GreaterThan,
                    self::GreaterOrEqual, self::LessThan, self::LessOrEqual];
        }

        /** @return list<self> */
        public static function textual(): array
        {
            return [self::Equals, self::NotEquals, self::StartsWith,
                    self::Contains, self::EndsWith];
        }
    }

    $operators = $numeric ? CompareOperator::numeric() : CompareOperator::textual();

WHAT FIRES — an `[...]` array literal is flagged when ALL hold:

  1. It has 3 or more items (configurable `min_group`, default 3), and
     every item is a plain enum-case fetch (`Enum::Case`) of the SAME
     enum. A mix of enums, or any non-case item, disqualifies it.
  2. The same case-group appears 2 or more times across the codebase
     (configurable `min_reuse`, default 2). A single-use inline array is
     a one-off and is NOT flagged. The group is canonicalised as the
     sorted, de-duplicated set of `EnumFqcn::CaseName` strings, so order
     and repetition inside the array don't matter.
  3. It is NOT the haystack (2nd argument) of `in_array(...)` /
     `array_search(...)` — that one-of membership test belongs to the
     CompareSelf `equalsAny` rule; this rule must not double-flag it.
  4. It is NOT inside the enum's own file — that is exactly where the
     named-group accessor would live.

This is a cross-file rule: it needs the codebase index to know a group
is reused. Single-file runs (`--file`) have no index, so this prophet
stays SILENT — it cannot establish reuse from one file.

The fix is not auto-fixable: the group's NAME is semantic and can't be
inferred. The prophet points at the duplicate; you name it.

Configure via:

    Backend\PreferEnumCaseGroupsProphet::class => [
        'min_group' => 3,        // smallest inline group worth a name
        'min_reuse' => 2,        // how many sites before it's "reused"
        'exclude_enums' => [     // enums whose groups never get flagged
            // 'App\\Support\\SomeFlagEnum',
        ],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Cross-file rule: without an index we cannot establish reuse, so we
        // stay silent (single-file `--file` runs land here).
        if ($this->index === null) {
            return $this->righteous();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $minGroup = $this->minGroup();
        $minReuse = $this->minReuse();
        $excluded = $this->excludedEnums();

        $finder = new NodeFinder;
        $warnings = [];
        $seenLines = [];

        foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope, $localEnumFqcns]) {
            $needles = $this->membershipNeedles($finder, $scope);

            /** @var array<Expr\Array_> $arrays */
            $arrays = $finder->findInstanceOf($scope, Expr\Array_::class);

            foreach ($arrays as $array) {
                if ($needles->contains($array)) {
                    continue;
                }

                $resolved = EnumCaseGroup::resolve($array, $uses, $namespace, $minGroup);

                if ($resolved === null) {
                    continue;
                }

                // Exclusion 4: the enum's own file is where the accessor lives.
                if (isset($localEnumFqcns[$resolved['fqcn']])) {
                    continue;
                }

                if (in_array($resolved['fqcn'], $excluded, true)) {
                    continue;
                }

                $key = EnumCaseGroup::canonicalKey($resolved);
                $count = $this->index->enumCaseGroupCount($key);

                if ($count < $minReuse) {
                    continue;
                }

                $line = $array->getStartLine();

                // De-dupe: one finding per array literal.
                if (isset($seenLines[$line . ':' . $key])) {
                    continue;
                }

                $seenLines[$line . ':' . $key] = true;
                $warnings[] = $this->warningFor($resolved, $count, $line);
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @param  array{fqcn: string, cases: list<string>}  $resolved
     */
    private function warningFor(array $resolved, int $count, int $line): Warning
    {
        $short = $this->shortName($resolved['fqcn']);
        $caseCount = count(array_unique($resolved['cases']));
        $caseList = implode(', ', array_map(
            fn (string $case): string => $short . '::' . $case,
            array_values(array_unique($resolved['cases'])),
        ));

        $message = sprintf(
            'Inline group of %d %s cases [%s] is duplicated (%d sites) — give it a name on the enum '
            . '(e.g. `%s::someGroup(): array`) and call that instead of re-inlining the subset.',
            $caseCount,
            $short,
            $caseList,
            $count,
            $short,
        );

        $symbol = EnumCaseGroup::canonicalKey($resolved);

        return $this->warningAt($line, $message, null, $symbol);
    }

    /**
     * Split the AST into namespace scopes, each carrying its use-map and the
     * FQCNs of enums defined directly in it.
     *
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: array<Node>, 3: array<string, true>}>
     */
    private function namespaceScopes(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $uses = $this->collectUses($scope);
            $localEnumFqcns = [];

            foreach ($scope as $stmt) {
                if ($stmt instanceof Node\Stmt\Enum_ && $stmt->name !== null) {
                    $short = $stmt->name->toString();
                    $fqcn = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $short : $short;
                    $localEnumFqcns[$fqcn] = true;
                }
            }

            $out[] = [$namespace, $uses, $scope, $localEnumFqcns];
        }

        return $out;
    }

    /**
     * The set of array literals that are membership-test haystacks (and so
     * belong to the CompareSelf rule, not this one).
     *
     * @param  array<Node>  $scope
     */
    private function membershipNeedles(NodeFinder $finder, array $scope): \SplObjectStorage
    {
        $needles = new \SplObjectStorage;

        /** @var array<Expr\FuncCall> $calls */
        $calls = $finder->findInstanceOf($scope, Expr\FuncCall::class);

        foreach ($calls as $call) {
            foreach ($finder->findInstanceOf([$call], Expr\Array_::class) as $array) {
                assert($array instanceof Expr\Array_);

                if (EnumCaseGroup::isMembershipNeedle($array, $call)) {
                    $needles->attach($array);
                }
            }
        }

        return $needles;
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>  short alias => FQCN
     */
    private function collectUses(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $fqcn;
            }
        }

        return $uses;
    }

    private function minGroup(): int
    {
        $value = $this->config('min_group', self::DEFAULT_MIN_GROUP);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_MIN_GROUP;
    }

    private function minReuse(): int
    {
        $value = $this->config('min_reuse', self::DEFAULT_MIN_REUSE);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_MIN_REUSE;
    }

    /**
     * @return list<string>
     */
    private function excludedEnums(): array
    {
        $value = $this->config('exclude_enums', []);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
