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
 * Flag a type union of more than `max_types` members (default 2) — `array |
 * string | null`, `int | float | string | null`. A value with three-plus shapes
 * is under-modelled: it pushes "what is this really?" onto every caller. When
 * the union is value-or-nothing (it includes null), the answer is an `Option`
 * (the null becomes the Option's absence, the rest its generic); otherwise a
 * small value object or a single type.
 */
#[IntroducedIn('1.81.0')]
class WideUnionTypeProphet extends PhpCommandment
{
    private const DEFAULT_MAX = 2;

    public function description(): string
    {
        return 'Avoid wide type unions — model value-or-nothing as an Option';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): ?Advisory
    {
        if ($this->isSin()) {
            return null;
        }

        return Advisory::make()
            ->applyWhen(
                'A parameter, return, or property type unions more than two members '
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
        $max = $this->maxTypes();

        return <<<SCRIPTURE
A union of three or more types is a value nobody has modelled — `array | string
| null` says "it might be one of these, you figure it out". Every caller then
re-derives what it actually is. Almost always it is really value-or-nothing, or
one concept wearing several disguises.

Bad:
    Option | array | string | null \$isVisibleRule = null,   // (and a contradiction)
    array | string | null \$isVisibleRule = null,            // the "fix" — still wide
    string | int | float | bool | null \$defaultValue = null,

Good — value-or-nothing is an Option (the null IS the absence):
    /** @var Option<array|string> */
    Option \$isVisibleRule,

Good — one concept wearing disguises is a value object:
    VisibilityRule \$isVisibleRule,

WHAT FIRES — a native type or a `@param`/`@return`/`@var` docblock type whose
TOP-LEVEL union has more than {$max} members (a union INSIDE a generic, like
`Option<array|string>`, does not count — that is correctly modelled).

WHAT DOES NOT — a simple nullable (`?T` / `T | null`), a two-member union, or a
union nested inside a generic.

Configure via:

    Backend\WideUnionTypeProphet::class => [
        'max_types' => {$max},
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $max = $this->maxTypes();
        $isSin = $this->isSin();
        $sins = [];
        $warnings = [];
        $flaggedLines = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\UnionType::class) as $union) {
            if (count($union->types) > $max) {
                $line = $union->getStartLine();
                $flaggedLines[$line] = true;
                $this->emit($line, count($union->types), $content, $isSin, $sins, $warnings);
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

                if ($count > $max) {
                    $this->emit($line, $count, $content, $isSin, $sins, $warnings);
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
            'This type unions %d members — a value with three-plus shapes is under-modelled and pushes "what is this really?" onto every caller. If it includes null it is value-or-nothing → `Option<rest>` (the null becomes the Option\'s absence). Otherwise make a small value object, or pick one type.',
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

        $atoms = array_filter(array_map('trim', explode('|', ltrim($stripped, '?'))));
        $count = count($atoms);

        // A leading `?` adds the implicit null member.
        return str_starts_with(ltrim($stripped), '?') ? $count + 1 : $count;
    }

    private function maxTypes(): int
    {
        $value = $this->config('max_types', self::DEFAULT_MAX);

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_MAX;
    }

    private function isSin(): bool
    {
        return $this->config('severity', 'warning') === 'sin';
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
