<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\ExtractsLineSnippet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\FallbackFingerprint;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use JesseGall\PhpTypes\T_String;

/**
 * Find fallback expressions (`??`, `?:`, full-ternary null/isset/empty checks)
 * whose computed chain is repeated verbatim across the codebase, so the prophet
 * can suggest hoisting them into a named static factory.
 *
 * Needs the cross-file index to know "how many times?" — without it (single
 * `--file` mode) it emits nothing, since repetition can't be established.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindRepeatedFallbacks implements Pipe
{
    use ExtractsLineSnippet;

    private ?CodebaseIndex $codebaseIndex = null;

    private int $minOccurrences = 2;

    /** @var list<string>  short names of configured null-object classes */
    private array $nullObjectShortNames = [];

    public function withCodebaseIndex(CodebaseIndex $index): self
    {
        $this->codebaseIndex = $index;

        return $this;
    }

    public function withMinOccurrences(int $min): self
    {
        $this->minOccurrences = max(2, $min);

        return $this;
    }

    /**
     * @param  list<string>  $shortNames
     */
    public function withNullObjectShortNames(array $shortNames): self
    {
        $this->nullObjectShortNames = $shortNames;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null || $this->codebaseIndex === null) {
            return $input->with(matches: []);
        }

        $nodes = (new NodeFinder)->find(
            $input->ast,
            FallbackFingerprint::qualifies(...),
        );

        $matches = [];
        $seen = [];

        foreach ($nodes as $node) {
            $parts = FallbackFingerprint::parts($node);

            if ($parts === null || $this->isNullObjectFallback($parts['fallback'], $input->useStatements)) {
                continue; // null-object fallbacks belong to the null-object prophets
            }

            $fingerprint = FallbackFingerprint::fingerprint($node, $input->content);

            if ($fingerprint === null) {
                continue;
            }

            $occurrences = $this->codebaseIndex->fallbackOccurrences($fingerprint);

            if (count($occurrences) < $this->minOccurrences) {
                continue;
            }

            $line = $node->getStartLine();
            $dedupeKey = $fingerprint . '@' . $line;

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $matches[] = new MatchResult(
                name: $parts['op'],
                pattern: T_String::empty(),
                match: $this->source($input->content, $node),
                line: $line,
                offset: null,
                content: $this->lineSnippet($input->content, $line),
                groups: [
                    'op' => $parts['op'],
                    'expr' => $this->source($input->content, $node),
                    'count' => (string) count($occurrences),
                    'others' => $this->otherLocations($occurrences, $input->filePath, $line),
                    'suggestion' => $this->suggestedFactory($parts['left'], $parts['fallback']),
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function isNullObjectFallback(Expr $fallback, array $useStatements): bool
    {
        if (! $fallback instanceof Expr\New_ || ! $fallback->class instanceof Node\Name) {
            return false;
        }

        $short = $fallback->class->getLast();

        if (str_starts_with($short, 'Null')) {
            return true;
        }

        return in_array($short, $this->nullObjectShortNames, true);
    }

    /**
     * Best-effort name for the extracted factory: the home class is the
     * leftmost static call's class; the method name is the value chain's call
     * names + `Or` + the fallback's call/new name.
     */
    private function suggestedFactory(Expr $value, Expr $fallback): string
    {
        $home = $this->leftmostStaticClass($value);
        $valueMethods = $this->callNamesInOrder($value);
        $fallbackName = $this->firstCallOrNewName($fallback);

        if ($valueMethods === [] || $fallbackName === null) {
            return 'a named static factory';
        }

        $method = $this->camel($valueMethods) . 'Or' . ucfirst($fallbackName);

        return $home !== null ? "{$home}::{$method}()" : "a named static factory like ::{$method}()";
    }

    private function leftmostStaticClass(Expr $value): ?string
    {
        /** @var list<Expr\StaticCall> $calls */
        $calls = (new NodeFinder)->findInstanceOf($value, Expr\StaticCall::class);

        $best = null;
        $bestPos = PHP_INT_MAX;

        foreach ($calls as $call) {
            $pos = $call->getStartFilePos() ?? PHP_INT_MAX;

            if ($call->class instanceof Node\Name && $pos < $bestPos) {
                $best = $call->class->getLast();
                $bestPos = $pos;
            }
        }

        return $best;
    }

    /**
     * Method/function names within the value expression, in source order.
     *
     * @return list<string>
     */
    private function callNamesInOrder(Expr $value): array
    {
        $calls = (new NodeFinder)->find($value, static fn (Node $n): bool => $n instanceof Expr\StaticCall
            || $n instanceof Expr\MethodCall
            || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\FuncCall);

        $named = [];

        foreach ($calls as $call) {
            $name = $this->callName($call);

            if ($name !== null) {
                // Sort by the method NAME token, not the call node — a chained
                // call spans its receiver, so `current()` and `child()` share a
                // start position; only the identifiers are in source order.
                $named[] = [$this->callNamePosition($call), $name];
            }
        }

        usort($named, static fn ($a, $b) => $a[0] <=> $b[0]);

        return array_map(static fn ($entry) => $entry[1], $named);
    }

    private function firstCallOrNewName(Expr $fallback): ?string
    {
        if ($fallback instanceof Expr\New_ && $fallback->class instanceof Node\Name) {
            return lcfirst($fallback->class->getLast());
        }

        $call = (new NodeFinder)->findFirst($fallback, static fn (Node $n): bool => $n instanceof Expr\StaticCall
            || $n instanceof Expr\MethodCall
            || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\FuncCall);

        return $call instanceof Node ? $this->callName($call) : null;
    }

    private function callNamePosition(Node $call): int
    {
        $name = null;

        if ($call instanceof Expr\FuncCall && $call->name instanceof Node\Name) {
            $name = $call->name;
        } elseif (($call instanceof Expr\StaticCall
            || $call instanceof Expr\MethodCall
            || $call instanceof Expr\NullsafeMethodCall)
            && $call->name instanceof Node\Identifier
        ) {
            $name = $call->name;
        }

        return $name?->getStartFilePos() ?? $call->getStartFilePos() ?? 0;
    }

    private function callName(Node $call): ?string
    {
        if ($call instanceof Expr\FuncCall) {
            return $call->name instanceof Node\Name ? $call->name->getLast() : null;
        }

        if (($call instanceof Expr\StaticCall
            || $call instanceof Expr\MethodCall
            || $call instanceof Expr\NullsafeMethodCall)
            && $call->name instanceof Node\Identifier
        ) {
            return $call->name->toString();
        }

        return null;
    }

    /**
     * @param  list<string>  $names
     */
    private function camel(array $names): string
    {
        $first = array_shift($names);

        return $first . implode(T_String::empty(), array_map(ucfirst(...), $names));
    }

    /**
     * @param  list<array{file: string, line: int}>  $occurrences
     */
    private function otherLocations(array $occurrences, string $currentFile, int $currentLine): string
    {
        $others = [];

        foreach ($occurrences as $occurrence) {
            if ($occurrence['file'] === $currentFile && $occurrence['line'] === $currentLine) {
                continue;
            }

            $others[] = basename($occurrence['file']) . ':' . $occurrence['line'];

            if (count($others) >= 3) {
                break;
            }
        }

        return implode(', ', $others);
    }

    private function source(string $content, Node $node): string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if ($start === null || $end === null || $start < 0 || $end < $start) {
            return '?';
        }

        return trim(substr($content, $start, $end - $start + 1));
    }

}
