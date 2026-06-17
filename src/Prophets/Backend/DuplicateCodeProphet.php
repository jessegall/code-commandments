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
 */
#[IntroducedIn('1.64.0')]
class DuplicateCodeProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_MIN_LINES = 5;

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

The fix is not auto-fixable — WHERE the shared logic should live (a private
method, a trait, a collaborator) is a design decision. Extract it to one home
and call that from both sites.

WHAT FIRES — a method body of >= `min_lines` printed lines (default 5) whose
canonical fingerprint matches at least one OTHER method in the scroll.

WHAT DOES NOT — bodies shorter than `min_lines`, and (because it is a
cross-file rule) anything when no codebase index could be built.

Configuration:

    Backend\DuplicateCodeProphet::class => [
        'min_lines' => 5,   // smallest duplicated body worth extracting
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
            $body = MethodBodyHash::of($method, $minLines);

            if ($body === null) {
                continue;
            }

            $line = $method->getStartLine();

            $others = array_values(array_filter(
                $this->index->methodBodyOccurrences($body['hash']),
                fn (array $occ): bool => ! ((realpath($occ['file']) ?: $occ['file']) === $self && $occ['line'] === $line),
            ));

            if ($others === []) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $line,
                $this->messageFor($method->name->toString(), $body['lines'], $others),
                null,
                'duplicate:' . $body['hash'],
            );
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
