<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Vue\Element;

/**
 * A `v-if` / `v-else-if` [/ `v-else`] chain read off the template as a switch: one
 * subject, equality-tested against a case per branch. Shared by the detector (which
 * just asks "is this a chain?") and the {@see \JesseGall\CodeCommandments\Cli\Rewriting\SwitchCaseScribe}
 * (which needs the subject, the case keys, and each branch element to rewrite).
 *
 * {@see at} returns null unless the head is a `v-if` equality whose every `v-else-if`
 * tests the SAME subject, across at least two cases — so a genuine conditional (mixed
 * subjects, `>`/method guards, a lone `v-if`) is never mistaken for a switch.
 */
final class SwitchCaseChain
{
    private const int CASES = 2;

    /**
     * @param  list<array{key: ?string, element: Element}>  $branches  key null = the `v-else` default
     */
    private function __construct(
        public readonly string $subject,
        public readonly array $branches,
    ) {}

    public static function at(Element $head): ?self
    {
        if (! $head->hasAttribute('v-if')) {
            return null;
        }

        [$subject, $key] = self::equality($head->attribute('v-if'));

        if ($subject === null) {
            return null;
        }

        $branches = [['key' => $key, 'element' => $head]];

        foreach ($head->followingElements() as $sibling) {
            if ($sibling->hasAttribute('v-else-if')) {
                [$next, $caseKey] = self::equality($sibling->attribute('v-else-if'));

                if ($next !== $subject) {
                    return null;
                }

                $branches[] = ['key' => $caseKey, 'element' => $sibling];

                continue;
            }

            if ($sibling->hasAttribute('v-else')) {
                $branches[] = ['key' => null, 'element' => $sibling];
            }

            break;
        }

        $cases = count(array_filter($branches, static fn (array $branch): bool => $branch['key'] !== null));

        return $cases >= self::CASES ? new self($subject, $branches) : null;
    }

    /**
     * Split a `subject === literal` test into [subject, caseKey] (caseKey unquoted),
     * or [null, null] when it isn't exactly that — the RHS must be ONE simple literal
     * (a string, number or identifier) and nothing may trail it, so a compound
     * `a === 'x' || a === 'y'` (not a single case) disqualifies the whole chain.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function equality(?string $expression): array
    {
        $literal = '\'[^\']*\'|"[^"]*"|-?\d+(?:\.\d+)?|[A-Za-z_$][\w$.]*';

        if ($expression !== null && preg_match('/^\s*([A-Za-z_$][\w$.]*)\s*===?\s*(' . $literal . ')\s*$/', $expression, $match) === 1) {
            return [$match[1], self::unquote($match[2])];
        }

        return [null, null];
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
