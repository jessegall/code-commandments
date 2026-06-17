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
 * Flag a type union by width — 2 members warn, 3+ sin (configurable). `array |
 * string | null`, `int | float | string | null`. A value with three-plus shapes
 * is under-modelled: it pushes "what is this really?" onto every caller. When
 * the union is value-or-nothing (it includes null), the answer is an `Option`
 * (the null becomes the Option's absence, the rest its generic); otherwise a
 * small value object or a single type.
 */
#[IntroducedIn('1.81.0')]
class WideUnionTypeProphet extends PhpCommandment
{
    private const DEFAULT_WARN_AT = 2;

    private const DEFAULT_SIN_AT = 3;

    public function description(): string
    {
        return 'Avoid wide type unions — model value-or-nothing as an Option';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A parameter, return, or property type unions two or more members '
                . '(`array | string | null`) — an under-modelled value that forces '
                . 'every caller to re-decide what it is.'
            )
            ->leaveWhen(
                'It is a genuinely open scalar value (a config primitive) where any '
                . 'modelling would be artificial — and even then, prefer wrapping the '
                . 'absence in an Option.'
            )
            ->whenUnsure(
                'If the union includes null, it is value-or-nothing → `Option<rest>`. '
                . 'If several shapes are really one concept, make a value object. '
                . 'If it is two shapes that should be one, pick one.'
            );
    }

    public function detailedDescription(): string
    {
        $warnAt = $this->warnThreshold();
        $sinAt = $this->sinThreshold();

        return <<<SCRIPTURE
A type union is a value nobody has modelled — `array | string | null` says "it
might be one of these, you figure it out", and every caller re-derives what it
actually is. Almost always it is really value-or-nothing, or one concept wearing
several disguises. The wider the union, the worse — so this rule graduates:

  - {$warnAt} union members  → WARNING (consider an Option / value object)
  - {$sinAt}+ union members  → SIN (clearly under-modelled — fix it)

Bad:
    Option | array | string | null \$isVisibleRule = null,   // (and a contradiction)
    array | string | null \$isVisibleRule = null,            // 3+ → sin
    string | int \$value,                                    // 2 → warning

Good — value-or-nothing is an Option (the null IS the absence):
    /** @var Option<array|string> */
    Option \$isVisibleRule,

Good — one concept wearing disguises is a value object:
    VisibilityRule \$isVisibleRule,

WHAT FIRES — a native type or a `@param`/`@return`/`@var` docblock type whose
TOP-LEVEL union has >= {$warnAt} members (a union INSIDE a generic, like
`Option<array|string>`, does not count — that is correctly modelled).

WHAT DOES NOT — a simple nullable (`?T`), a union nested inside a generic, or —
when the warning band is disabled — a union below the sin threshold.

Configure via:

    Backend\WideUnionTypeProphet::class => [
        'warn_at_types' => {$warnAt},   // 0 (or warnings_enabled => false) disables warnings
        'sin_at_types'  => {$sinAt},
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnAt = $this->warnThreshold();
        $sinAt = $this->sinThreshold();
        $floor = $warnAt > 0 ? min($warnAt, $sinAt) : $sinAt;
        $sins = [];
        $warnings = [];
        $flaggedLines = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\UnionType::class) as $union) {
            $count = count($union->types);

            if ($count >= $floor) {
                $line = $union->getStartLine();
                $flaggedLines[$line] = true;
                $this->emit($line, $count, $content, $count >= $sinAt, $sins, $warnings);
            }
        }

        // Docblock pass — the same wide union in @param/@return/@var, after
        // stripping generics (so `Option<array|string>` is not counted).
        foreach (explode("\n", $content) as $index => $text) {
            $line = $index + 1;

            if (isset($flaggedLines[$line])) {
                continue;
            }

            if (preg_match('/@(?:param|return|var)\s+(.+)$/', $text, $m)) {
                $count = $this->topLevelUnionCount($this->cleanDocType($m[1]));

                if ($count >= $floor) {
                    $this->emit($line, $count, $content, $count >= $sinAt, $sins, $warnings);
                }
            }
        }

        if ($sins !== []) {
            return $this->fallen($sins);
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @param  list<\JesseGall\CodeCommandments\Results\Sin>  $sins
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function emit(int $line, int $count, string $content, bool $isSin, array &$sins, array &$warnings): void
    {
        $message = sprintf(
            'This type unions %d members — a value worn as several shapes is under-modelled and pushes "what is this really?" onto every caller. If it includes null it is value-or-nothing → `Option<rest>` (the null becomes the Option\'s absence). If it is always present but one-of-N types, model it as a `Union` sum type (or a named value object). Or pick one type.',
            $count,
        );
        $snippet = $this->lineAt($content, $line);

        if ($isSin) {
            $sins[] = $this->sinAt($line, $message, $snippet, null, 'wide-union');
        } else {
            $warnings[] = $this->warningAt($line, $message, $snippet, 'wide-union');
        }
    }

    /**
     * The type portion of a docblock tag value: drop the variable name and any
     * trailing description, and strip whitespace (so a space inside a generic —
     * `array<string, int>` — does not truncate the type).
     */
    private function cleanDocType(string $rest): string
    {
        $type = preg_replace('/\$\w+.*$/', '', $rest) ?? $rest;

        return preg_replace('/\s+/', '', $type) ?? $type;
    }

    /**
     * The number of TOP-LEVEL members in a docblock union type, after stripping
     * generics — so `Option<array|string>` is 1, `array<string,int>|string|null`
     * is 3.
     */
    private function topLevelUnionCount(string $type): int
    {
        $stripped = $type;

        do {
            $previous = $stripped;
            $stripped = preg_replace('/<[^<>]*>/', '', $stripped) ?? $stripped;
        } while ($stripped !== $previous);

        // A leading `?` is the idiomatic nullable, not a union member — `?Foo`
        // counts as 1 (clean), `Foo|null` as 2.
        $atoms = array_filter(array_map('trim', explode('|', ltrim($stripped, '?'))));

        return count($atoms);
    }

    /**
     * Smallest union size that warns. 0 (or `warnings_enabled => false`) disables
     * the warning band — only sins fire then.
     */
    private function warnThreshold(): int
    {
        if ($this->config('warnings_enabled', true) === false) {
            return 0;
        }

        $value = $this->config('warn_at_types', self::DEFAULT_WARN_AT);

        return is_numeric($value) ? max(0, (int) $value) : self::DEFAULT_WARN_AT;
    }

    /**
     * Smallest union size that is a sin.
     */
    private function sinThreshold(): int
    {
        $value = $this->config('sin_at_types', self::DEFAULT_SIN_AT);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_SIN_AT;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
